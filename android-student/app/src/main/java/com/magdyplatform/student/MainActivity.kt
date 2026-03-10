package com.magdyplatform.student

import android.content.ActivityNotFoundException
import android.content.Intent
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.view.ViewGroup
import android.view.WindowManager
import android.webkit.CookieManager
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
import androidx.core.content.FileProvider
import androidx.core.view.isVisible
import com.google.android.material.progressindicator.LinearProgressIndicator
import java.io.File
import java.io.FileOutputStream
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

class MainActivity : AppCompatActivity() {
    private lateinit var webView: WebView
    private lateinit var progressIndicator: LinearProgressIndicator
    private var fileChooserCallback: ValueCallback<Array<Uri>>? = null
    private var customView: View? = null
    private var customViewCallback: WebChromeClient.CustomViewCallback? = null
    private var customViewContainer: FrameLayout? = null
    private var originalSystemUiVisibility: Int = 0

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
                    callback?.onCustomViewHidden()
                    return
                }

                originalSystemUiVisibility = decorView.systemUiVisibility
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
                webView.isVisible = false
                progressIndicator.isVisible = false
                decorView.systemUiVisibility =
                    View.SYSTEM_UI_FLAG_LAYOUT_STABLE or
                        View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION or
                        View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN or
                        View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                        View.SYSTEM_UI_FLAG_FULLSCREEN or
                        View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
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
        decorView?.systemUiVisibility = originalSystemUiVisibility
        webView.isVisible = true
    }

    private fun handlePdfNavigation(uri: Uri): Boolean {
        val pdfUri = resolveProtectedPdfUri(uri) ?: return false
        progressIndicator.isVisible = true
        progressIndicator.progress = 0

        Thread {
            val result = runCatching { downloadPdfToCache(pdfUri) }
            runOnUiThread {
                progressIndicator.isVisible = false
                result.onSuccess { file ->
                    openDownloadedPdf(file)
                }.onFailure {
                    Toast.makeText(
                        this,
                        R.string.pdf_open_failed,
                        Toast.LENGTH_SHORT
                    ).show()
                }
            }
        }.start()

        return true
    }

    private fun resolveProtectedPdfUri(uri: Uri): Uri? {
        val originalPath = uri.path ?: return null
        val path = originalPath.lowercase()
        return when {
            path.endsWith("/lecture_pdf_viewer.php") -> {
                uri.buildUpon()
                    .path(originalPath.replace("lecture_pdf_viewer.php", "lecture_pdf.php"))
                    .build()
            }
            path.endsWith("/lecture_pdf.php") || path.endsWith(".pdf") -> uri
            else -> null
        }
    }

    private fun downloadPdfToCache(uri: Uri): File {
        val connection = (URL(uri.toString()).openConnection() as HttpURLConnection).apply {
            instanceFollowRedirects = true
            connectTimeout = 15000
            readTimeout = 30000
            requestMethod = "GET"
            val cookies = CookieManager.getInstance().getCookie(uri.toString())
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

            val targetDir = File(cacheDir, "lecture-pdfs").apply { mkdirs() }
            val targetFile = File(targetDir, "lecture-${System.currentTimeMillis()}.pdf")
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
        val pdfUri = FileProvider.getUriForFile(
            this,
            "${BuildConfig.APPLICATION_ID}.fileprovider",
            file
        )

        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(pdfUri, "application/pdf")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }

        try {
            startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            Toast.makeText(
                this,
                R.string.pdf_viewer_not_found,
                Toast.LENGTH_SHORT
            ).show()
        }
    }
}
