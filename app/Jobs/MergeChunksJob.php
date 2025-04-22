<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MergeChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $totalChunks;

    public function __construct($totalChunks)
    {
        $this->totalChunks = $totalChunks;
    }

    public function handle()
    {
        $gsPath = '"C:\\Program Files\\gs\\gs10.05.0\\bin\\gswin64c.exe"';

        $inputFiles = '';
        $tempDir = storage_path('app/public');
        
        for ($i = 0; $i < $this->totalChunks; $i++) {
            $filePath = "$tempDir/emails_chunk_{$i}.pdf";

            if (file_exists($filePath)) {
                $inputFiles .= " \"$filePath\"";
            } else {
                Log::warning("Chunk not found: $filePath");
            }
        }

        if (empty(trim($inputFiles))) {
            Log::error("No input files found to merge.");
            return;
        }

        $outputPath = "$tempDir/emails_merged.pdf";

        $cmd = "$gsPath -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=\"$outputPath\" $inputFiles";

        Log::info("Running Ghostscript merge command...");

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0) {
            Log::info("PDFs merged successfully to: $outputPath");

            for ($i = 0; $i < $this->totalChunks; $i++) {
                Storage::delete("public/emails_chunk_{$i}.pdf");
                Storage::delete("chunks/done_{$i}.flag");
            }
        } else {
            Log::error("Ghostscript failed with code $returnCode: " . implode("\n", $output));
        }
    }
}
