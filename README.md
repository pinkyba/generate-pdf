# PDF Generation from Gmail Correspondence

1. Handling Large Files: I explored both DOMPDF and TCPDF for PDF generation, but these libraries are not ideal for processing large files due to performance limitations. For handling large files efficiently, Snappy (based on wkhtmltopdf) proved to be the best choice.

2. Optimizing Performance: To enhance performance, I implemented a strategy where I chunked email threads and used Laravel jobs to process each chunk separately. Afterward, the chunks were re-merged into a single PDF. This approach significantly improved the processing time.

3. Merging PDFs: While I experimented with setasign/FPDF for PDF merging, I found that using an external command, specifically Ghostscript, provides much better performance and is more suitable for handling large PDF files efficiently.


Laravel version: ^10.10

PHP version: ^8.1


Dependencies:
Gmail API: google/apiclient

PDF export: laravel-snappy

Merge pdf: setasign/fpdf


1. git clone
2. composer install
4. php artisan serve & php artisan queue:work
