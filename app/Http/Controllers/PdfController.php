<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Spatie\PdfToImage\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Jobs\DispatchPdfJobs;
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
        $content = $this->buildHtmlFromStoredImages();
        session(['email_content' => $content]);
        return $this->sendEmail();
    }

    private function buildHtmlFromStoredImages(): string
    {
        $directory = storage_path('app/public/pdf_pages');
        $files = glob($directory . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        sort($files); 

        $html = "<div style='font-family: Arial, sans-serif;'>";

        foreach ($files as $index => $filePath) {
            $base64 = base64_encode(file_get_contents($filePath));
            // Log::info("Encoded image size: " . strlen($base64));
            $mime = mime_content_type($filePath) ?: 'image/jpeg';

            $html .= "<div style='page-break-after: always; text-align: center; margin: 20px 0;'>
                        <p><strong>Page " . ($index + 1) . "</strong></p>
                        <img src='data:{$mime};base64,{$base64}' style='max-width:100%; height:auto;' />
                    </div>";
        }

        $html .= "</div>";
        // file_put_contents(storage_path('app/public/email_preview.html'), $html);
        return $html;
    }


    private function sendEmailThread(Google_Service_Gmail $gmail, string $bodyHtml): array
    {
        $threadIds = ['1965e155ac24fe84','1965e156cc1d9dfc','1965e157c9ce749b','1965e2db3fe2abd2','1965e2dc515b4608','1965e2dd5c427fbd','1965e2de4551cba4','1965e2df6fbd9ff3','1965e2e09d1be71a','1965e2e1953c8143','1965e2e291738872','1965e2e3a84a31c2','1965e2e4a8bf8d6c','1965e2e5cc349eaf','1965e2e6d16feb9c','1965e155ac24fe84','1965e156cc1d9dfc','1965e157c9ce749b','1965e273a97dfdb2','1965e274ba3435cf','1965e275a4d0db75','1965e276d054598d','1965e277d8364d26','1965e278c1d38a37','1965e279d83fb155'];
        $threadId = null;

        // for ($i = 1; $i <= 12; $i++) {
        //     $from = ($i % 2 === 0) ? env('GMAIL_ADDRESS_TO') : env('GMAIL_ADDRESS_FROM');
        //     $to   = ($i % 2 === 0) ? env('GMAIL_ADDRESS_FROM') : env('GMAIL_ADDRESS_TO');

        //     $raw = $this->buildRawMessage($from, $to, "Email thread #{$i}", $bodyHtml, $threadId);
        //     $sent = $gmail->users_messages->send('me', $raw);

        //     $threadId = $sent->getThreadId();
        //     $threadIds[] = $threadId;
        // }
        Log::info($threadIds);
        return $threadIds;
    }

    private function buildRawMessage($from, $to, $subject, $bodyHtml, $threadId = null): Google_Service_Gmail_Message
    {
        $raw = "From: {$from}\r\n";
        $raw .= "To: {$to}\r\n";
        $raw .= "Subject: {$subject}\r\n";
        if ($threadId) {
            $raw .= "In-Reply-To: {$threadId}\r\n";
            $raw .= "References: {$threadId}\r\n";
        }
        $raw .= "MIME-Version: 1.0\r\n";
        $raw .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $raw .= $bodyHtml;

        return new Google_Service_Gmail_Message([
            'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')
        ]);
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

        $token = $this->client->fetchAccessTokenWithAuthCode($request->query('code'));
        $this->client->setAccessToken($token);

        $gmail = new Google_Service_Gmail($this->client);
        $content = session('email_content');

        try {
            $threadIds = $this->sendEmailThread($gmail, $content);
            $accessToken = $this->client->getAccessToken();
            // Log::info($threadIds);
            DispatchPdfJobs::dispatch($accessToken, $threadIds);

            return response()->json(['status' => 'PDF generation started. Please check the status later.']);

        } catch (\Exception $e) {
            return 'An error occurred: ' . $e->getMessage();
        }
    }
}
