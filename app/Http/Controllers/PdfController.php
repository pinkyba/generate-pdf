<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Smalot\PdfParser\Parser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Log;

class PdfController extends Controller
{

    private $client;
    public function __construct() {
        $this->client = new Google_Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope([
            Google_Service_Gmail::GMAIL_SEND,
            Google_Service_Gmail::GMAIL_READONLY,
        ]);
    }

    public function generatePdf()
    {
        $content = $this->fetchContent(storage_path('app/content.pdf'));
        session(['email_content' => $content]);
        return $this->sendEmail();
    }

    private function fetchContent(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();
        
        return $text;
    }

    private function sendEmailThread(Google_Service_Gmail $gmail, string $bodyText): array
    {
        $threadIds = [];

        for ($i = 1; $i <= 25; $i++) {
            $raw = $this->buildRawMessage(
                env('GMAIL_ADDRESS_FROM'),
                env('GMAIL_ADDRESS_TO'),
                "Email thread #{$i}",
                $bodyText
            );
            $sent = $gmail->users_messages->send('me', $raw);
            $threadIds[] = $sent->getThreadId();
            usleep(500000);
        }

        return $threadIds;
    }

    private function buildRawMessage($from, $to, $subject, $body, $threadId = null): Google_Service_Gmail_Message
    {
        $raw = "From: {$from}\r\n";
        $raw .= "To: {$to}\r\n";
        $raw .= "Subject: {$subject}\r\n";
        if ($threadId) {
            $raw .= "In-Reply-To: {$threadId}\r\n";
            $raw .= "References: {$threadId}\r\n";
        }
        $raw .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $raw .= $body;
    
        return new Google_Service_Gmail_Message([
            'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')
        ]);
    }

    private function fetchAllThreadsHtml(Google_Service_Gmail $gmail, array $threadIds): string
    {
        $html = '<h1>All Email Threads</h1>';

        foreach ($threadIds as $index => $threadId) {
            $thread = $gmail->users_threads->get('me', $threadId);
            $html .= "<h2>Thread #".($index + 1)."</h2>";

            foreach ($thread->getMessages() as $msg) {
                $headers = collect($msg->getPayload()->getHeaders())
                    ->keyBy('name')
                    ->map->value;

                $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $msg->getPayload()->getBody()->getData()));
                $html .= "<h3>{$headers['Subject']} ({$headers['From']} → {$headers['To']})</h3>";
                $html .= "<pre>" . htmlentities(substr($body, 0, 2000)) . "</pre><hr>";
            }
        }

        return $html;
    }

    private function makePdf(string $html): void
    {
        $targetSize = 70 * 1024 * 1024;
        $filler = str_repeat('<p style="page-break-after: always;">.</p>', 1000);

        while (strlen($html) < $targetSize) {
            $html .= $filler;
        }

        $pdf = PDF::loadHTML($html);
        $pdf->save(storage_path('app/public/emails.pdf'));
    }

    public function sendEmail() {
        $authUrl = $this->client->createAuthUrl();
        return redirect($authUrl);
    }

    public function handleCallback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/')->with('error', 'Authorization code not available');
        }

        // Exchange the code for access token
        $token = $this->client->fetchAccessTokenWithAuthCode($request->query('code'));
        $this->client->setAccessToken($token);

        $gmail = new Google_Service_Gmail($this->client);
        $content = session('email_content');
        try {
            $threadIds = $this->sendEmailThread($gmail, $content);
            $html = $this->fetchAllThreadsHtml($gmail, $threadIds);
            $this->makePdf($html);

            return response()->download(storage_path('app/public/emails.pdf'));
        } catch (\Exception $e) {
            return 'An error occurred: ' . $e->getMessage();
        }
    }
}
