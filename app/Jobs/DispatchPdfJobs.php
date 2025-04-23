<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class DispatchPdfJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $accessToken;
    protected $threadIds;

    public function __construct(array $accessToken, array $threadIds)
    {
        $this->accessToken = $accessToken;
        $this->threadIds = $threadIds;
    }

    public function handle()
    {
        // $this->threadIds = ["1965a64516b4d688","1965a6452de3020c","1965a64555d1ca89","1965a6456303f387","1965a645800f854c","1965a645b89f2399","1965a645c6e6c7ad","1965a645fa1e29bb","1965a64607dbb3ee","1965a6463afe3a30","1965a6465791a2eb","1965a6466377cf2d","1965a646b7ad64e6","1965a646dd32fa84","1965a646eb584b4b","1965a64716bab257","1965a64734e66694","1965a6474df2371a","1965a64777498eba","1965a6478abfea6d","1965a647a6a7c072","1965a647dd7ae923","1965a647eb8788df","1965a648025a4f6d","1965a64822b2a05f"];
        // Log::info(json_encode($this->threadIds));
        $chunks = 10;
        $totalPages = 10;
        $chunkSize = $totalPages / $chunks;

        // for ($i = 0; $i < $chunks; $i++) {
        //     GeneratePdfChunkJob::dispatch(
        //         $this->accessToken,
        //         $this->threadIds,
        //         $i * $chunkSize,
        //         $chunkSize,
        //         $i
        //     );
        // }
        foreach ($this->threadIds as $index => $threadId) {
            GeneratePdfChunkJob::dispatch(
                $this->accessToken,
                [$threadId],  // Pass one thread ID at a time
                0,            // Start from 0 if per-thread pagination isn't needed
                1,            // Handle 1 thread per job
                $index        // Use $index as chunk index
            );
        }
    }
}
