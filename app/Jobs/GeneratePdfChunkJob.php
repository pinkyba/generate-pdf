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
        $html = '';

        foreach ($threadIds as $index => $threadId) {
            $thread = $gmail->users_threads->get('me', $threadId);
            $html .= "<h2>Thread #" . ($index + 1) . "</h2>";

            foreach ($thread->getMessages() as $msg) {
                $headers = collect($msg->getPayload()->getHeaders())->keyBy('name')->map->value;
                $body = base64_decode(strtr($msg->getPayload()->getBody()->getData(), '-_', '+/'));
                $html .= "<h3>{$headers['Subject']} ({$headers['From']} → {$headers['To']})</h3>";
                $html .= "<pre>" . htmlentities(substr($body, 0, 2000)) . "</pre><hr>";
            }
        }

        return $html;
    }

    private function makeChunkPdf(string $threadHtml): void
    {
        $html = '';

        for ($i = $this->start; $i < $this->start + $this->limit; $i++) {
            $html .= "<div style='page-break-after: always;'><strong>Page #{$i}</strong>{$threadHtml}</div>";
        }

        PDF::setTimeout(120);
        PDF::setOption('print-media-type', false);
        $pdf = PDF::loadHTML($html);
        $filename = "emails_chunk_{$this->chunkIndex}.pdf";
        $path = storage_path("app/public/{$filename}");

        $pdf->save($path);
       
        if ($this->chunkIndex == 9) {
            MergeChunksJob::dispatch($this->chunkIndex+1); 
        }
    }
}
