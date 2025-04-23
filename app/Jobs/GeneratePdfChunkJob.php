<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google_Service_Gmail;
use Google_Client;
use PDF;
use Storage;
use Log;

class GeneratePdfChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $accessToken;
    protected $threadIds;
    protected $start;
    protected $limit;
    protected $chunkIndex;

    public function __construct(array $accessToken, array $threadIds, int $start, int $limit, int $chunkIndex)
    {
        $this->accessToken = $accessToken;
        $this->threadIds = $threadIds;
        $this->start = $start;
        $this->limit = $limit;
        $this->chunkIndex = $chunkIndex;
    }

    public function handle()
    {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->addScope([Google_Service_Gmail::GMAIL_SEND, Google_Service_Gmail::GMAIL_READONLY]);
        $client->setAccessToken($this->accessToken);

        $gmail = new Google_Service_Gmail($client);

        $html = $this->fetchAllThreadsHtml($gmail, $this->threadIds);

        $this->makeChunkPdf($html);
    }

    private function fetchAllThreadsHtml(Google_Service_Gmail $gmail, array $threadIds): string
    {
        // Read the content of the pre-generated email preview HTML file
        $emailPreviewHtml = file_get_contents(storage_path('app/public/email_preview.html'));

        $html = '<h1>All Email Threads</h1>';

        foreach ($threadIds as $index => $threadId) {
            $thread = $gmail->users_threads->get('me', $threadId);
            
            // Loop through each message in the thread
            foreach ($thread->getMessages() as $msg) {
                $payload = $msg->getPayload();
                $headers = collect($payload->getHeaders())->keyBy('name')->map->value;

                // Add subject, from, and to to the email content
                $html .= "<h3> Subject: {$headers['Subject']} </h3>";
                $html .= "<h4> From: {$headers['From']} </h4>";
                $html .= "<h4> To: {$headers['To']} </h4>";

                // Add the pre-generated HTML content (email preview)
                $html .= "<div style='border:1px solid #ccc; margin-bottom:20px; padding:10px;'>{$emailPreviewHtml}</div><hr>";
            }
        }

        return $html;
    }



    private function makeChunkPdf(string $threadHtml): void
    {
        $html = '';

        // for ($i = $this->start; $i < $this->start + $this->limit; $i++) {
        //     $html .= "<div style='page-break-after: always;'><strong>Page #{$i}</strong>{$threadHtml}</div>";
        // }
        $html .= "<div style='page-break-after: always;'><strong>Email #" . ($this->chunkIndex + 1) . "</strong>{$threadHtml}</div>";

        PDF::setTimeout(1200);
        PDF::setOption('print-media-type', false);
        $pdf = PDF::loadHTML($html);
        Log::info('index: '.$this->chunkIndex);
        $filename = "emails_chunk_{$this->chunkIndex}.pdf";
        $path = storage_path("app/public/{$filename}");

        $pdf->save($path);
       
        if ($this->chunkIndex == 24) {
            MergeChunksJob::dispatch($this->chunkIndex+1); 
        }
    }
}
