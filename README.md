# PDF Generation from Gmail Correspondence

1. Handling Large Files: I explored both DOMPDF and TCPDF for PDF generation, but these libraries are not ideal for processing large files due to performance limitations. For handling large files efficiently, Snappy (based on wkhtmltopdf) proved to be the best choice.

2. Optimizing Performance: To enhance performance, I implemented a strategy where I chunked email threads and used Laravel jobs to process each chunk separately. Afterward, the chunks were re-merged into a single PDF. This approach significantly improved the processing time.

3. Merging PDFs: While I experimented with setasign/FPDF for PDF merging, I found that using an external command, specifically Ghostscript, provides much better performance and is more suitable for handling large PDF files efficiently.


Laravel version: ^10.10

PHP version: ^8.1


# Dependencies:

Gmail API: google/apiclient

PDF export: laravel-snappy

Merge pdf: setasign/fpdf

# Run project
1. git clone
2. composer install
4. php artisan serve & php artisan queue:work

# Runtime performance
  2025-04-22 00:26:36 App\Jobs\DispatchPdfJobs ............................................................ RUNNING
  
  2025-04-22 00:26:37 App\Jobs\DispatchPdfJobs ...................................................... 325.62ms DONE
  
  2025-04-22 00:26:37 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:27:42 App\Jobs\GeneratePdfChunkJob ..................................................... 1m 4s DONE

  2025-04-22 00:27:42 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:28:46 App\Jobs\GeneratePdfChunkJob ..................................................... 1m 3s DONE
  
  2025-04-22 00:28:46 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:29:49 App\Jobs\GeneratePdfChunkJob ..................................................... 1m 3s DONE
  
  2025-04-22 00:29:50 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:30:13 App\Jobs\GeneratePdfChunkJob ....................................................... 23s DONE
  
  2025-04-22 00:30:13 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:31:42 App\Jobs\GeneratePdfChunkJob .................................................... 1m 28s DONE
  
  2025-04-22 00:31:42 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:32:19 App\Jobs\GeneratePdfChunkJob ....................................................... 37s DONE
  
  2025-04-22 00:32:19 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:32:46 App\Jobs\GeneratePdfChunkJob ....................................................... 26s DONE
  
  2025-04-22 00:32:46 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:33:10 App\Jobs\GeneratePdfChunkJob ....................................................... 23s DONE
  
  2025-04-22 00:33:10 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:33:33 App\Jobs\GeneratePdfChunkJob ....................................................... 23s DONE
  
  2025-04-22 00:33:33 App\Jobs\GeneratePdfChunkJob ........................................................ RUNNING
  
  2025-04-22 00:33:56 App\Jobs\GeneratePdfChunkJob ....................................................... 22s DONE
  
  2025-04-22 00:33:56 App\Jobs\MergeChunksJob ............................................................. RUNNING
  
  2025-04-22 00:39:40 App\Jobs\MergeChunksJob ......................................................... 5m 44s DONE

  
