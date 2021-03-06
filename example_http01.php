<?php

/*
 * Usage:
 *
 * $ composer install
 * php example.php example.com 1
 * # Copy generated file under public folder on .well-known/acme-challenge folder from you domain webroot.
 * php example.php example.com 2
 * # Get the certificate files on cert folder
 */

use GuzzleHttp\Exception\ClientException;
use Nexy\NexyCrypt\Authorization\Challenge\Http01Challenge;
use Nexy\NexyCrypt\NexyCrypt;

require_once __DIR__.'/vendor/autoload.php';

if ($argc < 3) {
    echo 'You have to pass domain and step arguments.'.PHP_EOL;
    exit(1);
}

$domains = [];
for ($a = 1; $a < $argc - 1; ++$a) {
    $domains[] = $argv[$a];
}
$step = intval($argv[$a]);

// First commented line is for production.
//$client = new NexyCrypt();
$client = new NexyCrypt(null, 'https://acme-staging.api.letsencrypt.org/');

try {
    $client->register();
    $client->agreeTerms();

    if (1 === $step) {
        @mkdir('public');

        foreach ($domains as $domain) {
            $authorization = $client->authorize($domain);

            $challenge = $authorization->getChallenges()->getHttp01();

            @mkdir('public/'.$domain);
            file_put_contents('public/'.$domain.'/'.$challenge->getFileName(), $challenge->getFileContent());
            file_put_contents('public/'.$domain.'/challenge', serialize($challenge));
        }
    }

    if (2 === $step) {
        foreach ($domains as $domain) {
            /** @var Http01Challenge $challenge */
            $challenge = unserialize(file_get_contents('public/'.$domain.'/challenge'));

            $client->verifyChallenge($challenge);
        }

        @mkdir('cert');

        $certificate = $client->generateCertificate($domains);
        foreach ($certificate->getFilesArray() as $filename => $content) {
            file_put_contents('cert/'.$filename, $content);
        }

        $certificate = $client->signCertificate($certificate);
        foreach ($certificate->getFilesArray() as $filename => $content) {
            file_put_contents('cert/'.$filename, $content);
        }
    }
} catch (ClientException $e) {
    dump(json_decode($e->getResponse()->getBody()->getContents(), true));

    exit(1);
}
