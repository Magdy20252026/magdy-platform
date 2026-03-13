package com.magdyplatform.student

import android.graphics.Bitmap
import android.graphics.Color
import android.graphics.pdf.PdfRenderer
import android.os.Bundle
import android.os.ParcelFileDescriptor
import android.widget.Button
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import java.io.File

class PdfViewerActivity : AppCompatActivity() {
    private lateinit var pdfImageView: ImageView
    private lateinit var pageIndicatorView: TextView
    private lateinit var previousButton: Button
    private lateinit var nextButton: Button

    private var fileDescriptor: ParcelFileDescriptor? = null
    private var pdfRenderer: PdfRenderer? = null
    private var currentPage: PdfRenderer.Page? = null
    private var currentBitmap: Bitmap? = null
    private var currentPageIndex = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_pdf_viewer)

        pdfImageView = findViewById(R.id.pdfImageView)
        pageIndicatorView = findViewById(R.id.pdfPageIndicator)
        previousButton = findViewById(R.id.pdfPreviousButton)
        nextButton = findViewById(R.id.pdfNextButton)

        findViewById<ImageButton>(R.id.pdfCloseButton).setOnClickListener {
            finish()
        }
        previousButton.setOnClickListener {
            showPage(currentPageIndex - 1)
        }
        nextButton.setOnClickListener {
            showPage(currentPageIndex + 1)
        }

        val pdfPath = intent.getStringExtra(EXTRA_PDF_PATH)?.trim().orEmpty()
        if (pdfPath.isEmpty()) {
            Toast.makeText(this, R.string.pdf_invalid_file, Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        val pdfFile = File(pdfPath)
        if (!pdfFile.isFile) {
            Toast.makeText(this, R.string.pdf_invalid_file, Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        val openResult = runCatching {
            fileDescriptor = ParcelFileDescriptor.open(pdfFile, ParcelFileDescriptor.MODE_READ_ONLY)
            val descriptor = fileDescriptor ?: error("Missing PDF descriptor")
            pdfRenderer = PdfRenderer(descriptor)
        }
        if (openResult.isFailure) {
            Toast.makeText(this, R.string.pdf_open_failed, Toast.LENGTH_SHORT).show()
            closeRenderer()
            finish()
            return
        }
        if ((pdfRenderer?.pageCount ?: 0) <= 0) {
            Toast.makeText(this, R.string.pdf_invalid_file, Toast.LENGTH_SHORT).show()
            closeRenderer()
            finish()
            return
        }

        showPage(0)
    }

    override fun onDestroy() {
        closeRenderer()
        super.onDestroy()
    }

    private fun showPage(index: Int) {
        val renderer = pdfRenderer ?: return
        if (index < 0 || index >= renderer.pageCount) return

        currentPage?.close()
        currentPage = renderer.openPage(index)
        currentPageIndex = index

        currentBitmap?.recycle()
        val page = currentPage ?: return
        val scale = resources.displayMetrics.density.coerceIn(MIN_RENDER_SCALE, MAX_RENDER_SCALE)
        val targetWidth = (page.width * scale).toInt().coerceAtLeast(1)
        val targetHeight = (page.height * scale).toInt().coerceAtLeast(1)
        currentBitmap = Bitmap.createBitmap(targetWidth, targetHeight, Bitmap.Config.RGB_565).apply {
            eraseColor(Color.WHITE)
        }
        val renderedBitmap = currentBitmap ?: return
        page.render(renderedBitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
        pdfImageView.setImageBitmap(renderedBitmap)

        pageIndicatorView.text = getString(
            R.string.pdf_page_indicator,
            currentPageIndex + 1,
            renderer.pageCount
        )
        previousButton.isEnabled = currentPageIndex > 0
        nextButton.isEnabled = currentPageIndex < renderer.pageCount - 1
    }

    private fun closeRenderer() {
        currentPage?.close()
        currentPage = null
        pdfRenderer?.close()
        pdfRenderer = null
        fileDescriptor?.close()
        fileDescriptor = null
        currentBitmap?.recycle()
        currentBitmap = null
    }

    companion object {
        const val EXTRA_PDF_PATH = "extra_pdf_path"
        private const val MIN_RENDER_SCALE = 1f
        private const val MAX_RENDER_SCALE = 2f
    }
}
