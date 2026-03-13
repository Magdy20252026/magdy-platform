package com.magdyplatform.student

import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.ActivityInfo
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.util.Log
import android.view.View
import android.view.ViewGroup
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.core.view.isVisible
import androidx.lifecycle.lifecycleScope
import com.google.android.material.progressindicator.LinearProgressIndicator
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var progressIndicator: LinearProgressIndicator
    private var fileChooserCallback: ValueCallback<Array<Uri>>? = null
    private var customView: View? = null
    private var customViewCallback: WebChromeClient.CustomViewCallback? = null
    private var customViewContainer: FrameLayout? = null
    private var previousRequestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
    private var landscapeVideoModeActive = false
    private var pdfOpenInFlight = false

    companion object {
        private const val TAG = "MainActivity"
        private const val PDF_CONNECT_TIMEOUT_MS = 15_000
        private const val PDF_READ_TIMEOUT_MS = 30_000
        private const val PDF_CACHE_DIR_NAME = "lecture-pdfs"
        private const val PDF_FILENAME_PREFIX = "lecture-"
        private const val PDF_FILE_EXTENSION = "pdf"
        private const val LECTURE_PDF_VIEWER_FILENAME = "lecture_pdf_viewer.php"
        private const val LECTURE_PDF_FILENAME = "lecture_pdf.php"
    }

    private val fileChooserLauncher =
        registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            val callback = fileChooserCallback ?: return@registerForActivityResult
            val uris = WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data)
            callback.onReceiveValue(uris)
            fileChooserCallback = null
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (BuildConfig.BLOCK_SCREEN_CAPTURE) {
            window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
        }

        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        progressIndicator = findViewById(R.id.progressIndicator)

        configureWebView()
        configureBackNavigation()

        if (savedInstanceState == null) {
            webView.loadUrl(BuildConfig.APP_START_URL)
        } else {
            webView.restoreState(savedInstanceState)
        }
    }

    @Suppress("SetJavaScriptEnabled")
    private fun configureWebView() {
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)
        webView.addJavascriptInterface(StudentAppBridge(), "StudentAppBridge")

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            loadsImagesAutomatically = true
            allowFileAccess = false
            allowContentAccess = true
            useWideViewPort = true
            loadWithOverviewMode = true
            builtInZoomControls = false
            displayZoomControls = false
            mediaPlaybackRequiresUserGesture = false
            javaScriptCanOpenWindowsAutomatically = true
            setSupportMultipleWindows(false)
            cacheMode = WebSettings.LOAD_DEFAULT
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url ?: return false
                val scheme = uri.scheme?.lowercase() ?: return false

                if (scheme == "http" || scheme == "https") {
                    if (request.isForMainFrame && handlePdfNavigation(uri)) {
                        return true
                    }
                    return false
                }

                return try {
                    startActivity(Intent(Intent.ACTION_VIEW, uri))
                    true
                } catch (_: ActivityNotFoundException) {
                    false
                }
            }

            override fun onPageFinished(view: WebView, url: String?) {
                super.onPageFinished(view, url)
                progressIndicator.isVisible = false
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                super.onProgressChanged(view, newProgress)
                progressIndicator.isVisible = newProgress < 100
                progressIndicator.progress = newProgress
            }

            override fun onShowCustomView(view: View?, callback: CustomViewCallback?) {
                if (view == null) {
                    callback?.onCustomViewHidden()
                    return
                }

                if (customView != null) {
                    callback?.onCustomViewHidden()
                    return
                }

                val decorView = window.decorView as? ViewGroup ?: run {
                    Log.w(TAG, "Unable to enter fullscreen because decorView is not a ViewGroup.")
                    callback?.onCustomViewHidden()
                    return
                }

                customView = view
                customViewCallback = callback
                customViewContainer = FrameLayout(this@MainActivity).apply {
                    setBackgroundColor(Color.BLACK)
                    addView(
                        view,
                        FrameLayout.LayoutParams(
                            ViewGroup.LayoutParams.MATCH_PARENT,
                            ViewGroup.LayoutParams.MATCH_PARENT
                        )
                    )
                }

                decorView.addView(
                    customViewContainer,
                    ViewGroup.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT
                    )
                )
                enterLandscapeVideoMode()
                webView.isVisible = false
                progressIndicator.isVisible = false
                WindowCompat.setDecorFitsSystemWindows(window, false)
                WindowInsetsControllerCompat(window, decorView).apply {
                    systemBarsBehavior =
                        WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                    hide(WindowInsetsCompat.Type.systemBars())
                }
            }

            override fun onHideCustomView() {
                hideCustomView()
            }

            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                this@MainActivity.fileChooserCallback?.onReceiveValue(null)
                this@MainActivity.fileChooserCallback = filePathCallback

                val chooserIntent = try {
                    fileChooserParams?.createIntent()
                        ?: Intent(Intent.ACTION_GET_CONTENT).apply {
                            addCategory(Intent.CATEGORY_OPENABLE)
                            type = "*/*"
                        }
                } catch (_: ActivityNotFoundException) {
                    this@MainActivity.fileChooserCallback = null
                    Toast.makeText(
                        this@MainActivity,
                        R.string.file_picker_not_found,
                        Toast.LENGTH_SHORT
                    ).show()
                    return false
                }

                return try {
                    fileChooserLauncher.launch(chooserIntent)
                    true
                } catch (_: ActivityNotFoundException) {
                    this@MainActivity.fileChooserCallback = null
                    Toast.makeText(
                        this@MainActivity,
                        R.string.file_picker_not_found,
                        Toast.LENGTH_SHORT
                    ).show()
                    false
                }
            }
        }
    }

    private fun configureBackNavigation() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (customView != null) {
                    hideCustomView()
                    return
                }
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })
    }

    override fun onSaveInstanceState(outState: Bundle) {
        webView.saveState(outState)
        super.onSaveInstanceState(outState)
    }

    override fun onDestroy() {
        fileChooserCallback?.onReceiveValue(null)
        fileChooserCallback = null
        hideCustomView()
        webView.stopLoading()
        webView.webChromeClient = null
        webView.webViewClient = WebViewClient()
        webView.destroy()
        super.onDestroy()
    }

    private fun hideCustomView() {
        val decorView = window.decorView as? ViewGroup
        customViewContainer?.let { container ->
            decorView?.removeView(container)
        }
        customViewContainer = null
        customView = null
        customViewCallback?.onCustomViewHidden()
        customViewCallback = null
        exitLandscapeVideoMode()
        decorView?.let {
            WindowCompat.setDecorFitsSystemWindows(window, true)
            WindowInsetsControllerCompat(window, it).show(WindowInsetsCompat.Type.systemBars())
        }
        webView.isVisible = true
    }

    private fun handlePdfNavigation(uri: Uri): Boolean {
        if (pdfOpenInFlight) return true
        val pdfUri = resolveProtectedPdfUri(uri) ?: return false
        pdfOpenInFlight = true
        progressIndicator.isVisible = true
        progressIndicator.progress = 0

        lifecycleScope.launch {
            try {
                val result = runCatching {
                    withContext(Dispatchers.IO) {
                        downloadPdfToCache(pdfUri)
                    }
                }
                if (isFinishing || isDestroyed) return@launch
                progressIndicator.isVisible = false
                result.onSuccess { file ->
                    openDownloadedPdf(file)
                }.onFailure {
                    Toast.makeText(
                        this@MainActivity,
                        R.string.pdf_open_failed,
                        Toast.LENGTH_SHORT
                    ).show()
                }
            } finally {
                pdfOpenInFlight = false
            }
        }

        return true
    }

    private fun enterLandscapeVideoMode() {
        if (landscapeVideoModeActive) return
        previousRequestedOrientation = requestedOrientation
        requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE
        landscapeVideoModeActive = true
    }

    private fun exitLandscapeVideoMode() {
        if (!landscapeVideoModeActive) return
        requestedOrientation = previousRequestedOrientation
        previousRequestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
        landscapeVideoModeActive = false
    }

    private fun resolveProtectedPdfUri(uri: Uri): Uri? {
        val originalPath = uri.path ?: return null
        val path = originalPath.lowercase()
        return when {
            path.endsWith("/$LECTURE_PDF_VIEWER_FILENAME") -> {
                val lastSlashIndex = originalPath.lastIndexOf('/')
                val directPdfPath = if (lastSlashIndex >= 0) {
                    originalPath.substring(0, lastSlashIndex + 1) + LECTURE_PDF_FILENAME
                } else {
                    LECTURE_PDF_FILENAME
                }
                uri.buildUpon()
                    .path(directPdfPath)
                    .build()
            }
            path.endsWith("/$LECTURE_PDF_FILENAME") || path.endsWith(".$PDF_FILE_EXTENSION") -> uri
            else -> null
        }
    }

    private fun downloadPdfToCache(uri: Uri): File {
        val requestUrl = uri.toString().trim()
        val connection = (URL(requestUrl).openConnection() as HttpURLConnection).apply {
            instanceFollowRedirects = true
            connectTimeout = PDF_CONNECT_TIMEOUT_MS
            readTimeout = PDF_READ_TIMEOUT_MS
            requestMethod = "GET"
            val cookies = CookieManager.getInstance().getCookie(requestUrl)
            if (!cookies.isNullOrBlank()) {
                setRequestProperty("Cookie", cookies)
            }
            setRequestProperty("Accept", "application/pdf,*/*")
            setRequestProperty("User-Agent", webView.settings.userAgentString ?: "")
        }

        try {
            connection.connect()
            if (connection.responseCode !in 200..299) {
                throw IOException("Unexpected response code: ${connection.responseCode}")
            }

            val targetDir = preparePdfCacheDir()
            val targetFile = File(targetDir, "$PDF_FILENAME_PREFIX${UUID.randomUUID()}.$PDF_FILE_EXTENSION")
            connection.inputStream.use { input ->
                FileOutputStream(targetFile).use { output ->
                    input.copyTo(output)
                }
            }

            return targetFile
        } finally {
            connection.disconnect()
        }
    }

    private fun openDownloadedPdf(file: File) {
        val intent = Intent(this, PdfViewerActivity::class.java).apply {
            putExtra(PdfViewerActivity.EXTRA_PDF_PATH, file.absolutePath)
        }
        startActivity(intent)
    }

    @Throws(IOException::class)
    private fun preparePdfCacheDir(): File {
        val targetDir = File(cacheDir, PDF_CACHE_DIR_NAME)
        if (!targetDir.exists() && !targetDir.mkdirs()) {
            throw IOException("Unable to create PDF cache directory: ${targetDir.absolutePath}")
        }

        targetDir.listFiles()?.forEach { cachedFile ->
            if (
                cachedFile.isFile &&
                cachedFile.name.startsWith(PDF_FILENAME_PREFIX) &&
                cachedFile.extension.equals(PDF_FILE_EXTENSION, ignoreCase = true)
            ) {
                if (!cachedFile.delete() && cachedFile.exists()) {
                    Log.w(TAG, "Failed to delete stale PDF cache: ${cachedFile.absolutePath}")
                }
            }
        }

        return targetDir
    }

    private inner class StudentAppBridge {
        @JavascriptInterface
        fun enterLandscapeVideoMode() {
            runOnUiThread {
                this@MainActivity.enterLandscapeVideoMode()
            }
        }

        @JavascriptInterface
        fun exitLandscapeVideoMode() {
            runOnUiThread {
                this@MainActivity.exitLandscapeVideoMode()
            }
        }

        @JavascriptInterface
        fun openProtectedPdf(url: String?) {
            val safeUrl = url?.trim()?.takeIf { it.isNotEmpty() } ?: return
            val parsedUri = runCatching { Uri.parse(safeUrl) }.getOrNull() ?: return
            runOnUiThread {
                handlePdfNavigation(parsedUri)
            }
        }
    }
}
