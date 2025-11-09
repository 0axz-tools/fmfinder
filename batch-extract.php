#!/usr/bin/env php
<?php

class SimpleEmailExtractor
{
    private $threads;
    private $resultFile = 'result.txt';
    private $contactPaths = [
        'contact', 'contact-us', 'contactus', 'contacts',
        'about', 'about-us', 'aboutus', 'info',
        'support', 'help', 'customer-service',
        'contact-form', 'contacto', 'kontak',
        'get-in-touch', 'connect', 'sales'
    ];

    public function run($filename, $threads = 10)
    {
        $this->threads = $threads;
        
        echo "Memulai ekstraksi email...\n";
        echo "File: $filename\n";
        echo "Threads: $threads\n";
        echo "Output: {$this->resultFile}\n\n";

        if (!file_exists($filename)) {
            die("ERROR: File $filename tidak ditemukan!\n");
        }

        $websites = $this->readWebsites($filename);
        $total = count($websites);
        
        echo "Total website: $total\n";
        echo "Memproses...\n\n";

        $this->processBatch($websites, $filename);
        
        echo "\nSelesai! Hasil disimpan di: {$this->resultFile}\n";
        
        // Hapus file sumber setelah selesai
        if (file_exists($filename)) {
            unlink($filename);
            echo "File $filename telah dihapus.\n";
        }
    }

    private function readWebsites($filename)
    {
        $websites = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_filter($websites, function($site) {
            $site = trim($site);
            return !empty($site) && strpos($site, '#') !== 0;
        });
    }

    private function processBatch($websites, $sourceFilename)
    {
        $chunks = array_chunk($websites, $this->threads);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            echo "Batch " . ($chunkIndex + 1) . "/" . count($chunks) . ": ";
            
            $results = [];
            $handles = [];
            $multi = curl_multi_init();

            // Setup multi curl
            foreach ($chunk as $index => $website) {
                $website = trim($website);
                $urls = $this->generateUrls($website);
                
                $handles[$website] = [];
                foreach ($urls as $url) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    curl_multi_add_handle($multi, $ch);
                    $handles[$website][] = $ch;
                }
            }

            // Execute multi curl
            $running = null;
            do {
                curl_multi_exec($multi, $running);
            } while ($running);

            // Process results
            foreach ($handles as $website => $curlHandles) {
                $allEmails = [];
                
                foreach ($curlHandles as $ch) {
                    $html = curl_multi_getcontent($ch);
                    $emails = $this->extractValidEmails($html);
                    $allEmails = array_merge($allEmails, $emails);
                    
                    curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                }
                
                $uniqueEmails = array_unique($allEmails);
                if (!empty($uniqueEmails)) {
                    $this->saveEmails($uniqueEmails);
                    echo "✓";
                } else {
                    echo ".";
                }
                
                // Hapus website yang sudah diproses dari file sumber
                $this->removeProcessedWebsite($sourceFilename, $website);
            }

            curl_multi_close($multi);
            echo "\n";
            
            // Delay antara batch
            if ($chunkIndex < count($chunks) - 1) {
                sleep(1);
            }
        }
    }

    private function removeProcessedWebsite($filename, $website)
    {
        if (!file_exists($filename)) {
            return;
        }
        
        // Baca semua baris dari file
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Filter untuk menghapus website yang sudah diproses
        $newLines = array_filter($lines, function($line) use ($website) {
            return trim($line) !== trim($website);
        });
        
        // Tulis kembali ke file
        if (!empty($newLines)) {
            file_put_contents($filename, implode("\n", $newLines) . "\n", LOCK_EX);
        } else {
            // Jika file kosong, hapus file
            unlink($filename);
        }
    }

    private function generateUrls($website)
    {
        $urls = [];
        $baseUrl = $this->normalizeUrl($website);
        
        // URL utama
        $urls[] = $baseUrl;
        
        // URL dengan path contact
        foreach ($this->contactPaths as $path) {
            $urls[] = $baseUrl . '/' . $path;
        }
        
        return $urls;
    }

    private function normalizeUrl($url)
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        return rtrim($url, '/');
    }

    private function extractValidEmails($html)
    {
        if (!$html) return [];
        
        // Pattern untuk email
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        preg_match_all($pattern, $html, $matches);
        
        $emails = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email));
                
                // Validasi email yang ketat
                if ($this->isValidRealEmail($email)) {
                    $emails[] = $email;
                }
            }
        }
        
        return array_unique($emails);
    }

    private function isValidRealEmail($email)
    {
        // Filter dasar untuk email valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Split email untuk cek domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        // Domain yang umum digunakan untuk contoh/placeholder
        $fakeDomains = [
            'example.com', 'email.com', 'mail.com', 'domain.com', 
            'yourdomain.com', 'yourmail.com', 'host.com', 
            'test.com', 'demo.com', 'sample.com'
        ];
        
        foreach ($fakeDomains as $fake) {
            if (strpos($domain, $fake) !== false) {
                return false;
            }
        }
        
        // Filter ekstensi file gambar dan asset
        $fileExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico',
            'tiff', 'psd', 'raw', 'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'zip', 'rar', 'mp3', 'mp4', 'avi', 'mov', 'wmv'
        ];
        
        foreach ($fileExtensions as $ext) {
            // Cek di domain atau local part
            if (preg_match('/\.' . $ext . '$/i', $domain) || 
                preg_match('/\.' . $ext . '$/i', $local)) {
                return false;
            }
        }
        
        // Filter pattern file asset (seperti @2x.png, @3x.jpg, dll)
        $assetPatterns = [
            '/@\d+x\.(png|jpg|jpeg|gif|webp|svg)/i', // @2x.png, @3x.jpg
            '/-\d+x\d+\.(png|jpg|jpeg|gif)/i',       // -300x300.jpg
            '/_\d+x\d+\.(png|jpg|jpeg|gif)/i',       // _100x100.png
            '/\.(png|jpg|jpeg|gif|webp|svg)$/i',     // langsung ekstensi gambar
            '/\d+@\d+x/',                           // pattern angka@angkax
            '/gold-trusted-service/',               // pattern khusus
            '/interest-free-box/',
            '/seen-on-tv-box/',
            '/emergencyresp/',
            '/innovation/',
            '/training/',
            '/qualityassurance/',
            '/ajax-loader/',
            '/logo.*@\d+x/'
        ];
        
        foreach ($assetPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return false;
            }
        }
        
        // Filter local part yang mencurigakan
        $suspiciousLocalParts = [
            'user', 'email', 'mail', 'test', 'demo', 'example',
            'info', 'admin', 'contact', 'support', 'hello', 
            'yourmail', 'tuemail', 'usuario', 'correo'
        ];
        
        // Jika local part hanya kata umum saja, skip
        if (in_array($local, $suspiciousLocalParts)) {
            // Tapi jangan skip jika domainnya valid
            $validTlds = ['com', 'org', 'net', 'edu', 'gov', 'co', 'io'];
            $domainParts = explode('.', $domain);
            $tld = end($domainParts);
            
            if (!in_array($tld, $validTlds)) {
                return false;
            }
        }
        
        // Filter domain dengan angka berlebihan
        if (preg_match('/\d{4,}/', $domain)) {
            return false;
        }
        
        // Filter email dengan karakter khusus berlebihan
        if (preg_match('/[._%+-]{3,}/', $local)) {
            return false;
        }
        
        // Validasi TLD
        $domainParts = explode('.', $domain);
        $tld = end($domainParts);
        
        // TLD harus minimal 2 karakter dan maksimal 6 karakter
        if (strlen($tld) < 2 || strlen($tld) > 6) {
            return false;
        }
        
        // TLD umum yang valid
        $commonTlds = [
            'com', 'org', 'net', 'edu', 'gov', 'mil', 'io', 'co',
            'info', 'biz', 'me', 'us', 'uk', 'ca', 'au', 'de',
            'fr', 'it', 'es', 'nl', 'se', 'no', 'dk', 'fi',
            'jp', 'cn', 'in', 'br', 'ru', 'mx', 'id', 'my'
        ];
        
        // Tidak perlu strict, tapi beri warning jika TLD tidak umum
        if (!in_array($tld, $commonTlds)) {
            // Bisa ditambahkan logging di sini jika perlu
        }
        
        return true;
    }

    private function saveEmails($emails)
    {
        $existingEmails = [];
        if (file_exists($this->resultFile)) {
            $existingContent = file_get_contents($this->resultFile);
            $existingEmails = array_filter(explode("\n", $existingContent));
        }
        
        $allEmails = array_merge($existingEmails, $emails);
        $uniqueEmails = array_unique($allEmails);
        
        // Sort emails
        sort($uniqueEmails);
        
        $content = implode("\n", $uniqueEmails) . "\n";
        file_put_contents($this->resultFile, $content, LOCK_EX);
    }
}

// Main execution
echo "===================================\n";
echo "    SIMPLE EMAIL EXTRACTOR\n";
echo "===================================\n";

if ($argc < 2) {
    echo "Penggunaan: php main.php <file_list> [threads]\n\n";
    echo "Contoh:\n";
    echo "  php main.php xaa\n";
    echo "  php main.php xaa 30\n";
    echo "  php main.php websites.txt 20\n";
    exit(1);
}

$filename = $argv[1];
$threads = isset($argv[2]) ? (int)$argv[2] : 10;

$extractor = new SimpleEmailExtractor();
$extractor->run($filename, $threads);
