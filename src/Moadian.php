<?php

namespace Arissystem\Moadian;

use DateTime;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Arissystem\Moadian\Api\Api;
use Arissystem\Moadian\Dto\Packet;
use Arissystem\Moadian\Dto\Token;
use Arissystem\Moadian\Services\EncryptionService;
use Arissystem\Moadian\Services\HttpClient;
use Arissystem\Moadian\Services\InvoiceIdService;
use Arissystem\Moadian\Services\SignatureService;

class Moadian
{
    private ?Token $token = null;
    protected string $publicKey;
    protected $privateKey;
    protected string $orgKeyId;
    protected string $username;
    protected string $baseURL = 'https://tp.tax.gov.ir';
    protected ?string $pkeyPass;

    public function __construct(
        string $publicKey,
        $privateKey,
        string $orgKeyId,
        string $username,
        ?string $pkeyPass = null,
        string $baseURL = 'https://tp.tax.gov.ir'
    ){
        $this->publicKey = $publicKey;
        $this->privateKey = openssl_get_privatekey($privateKey, $pkeyPass);
        $this->orgKeyId = $orgKeyId;
        $this->username = $username;
        $this->baseURL = $baseURL;
        $this->pkeyPass = $pkeyPass;
    }

    public function setToken(Token $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function sendInvoice(Packet $packet)
    {
        if (!$this->token) {
            throw new InvalidArgumentException("Set token before sending invoice!");
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->token->getToken(),
            'requestTraceId' => (string)Uuid::uuid4(),
            'timestamp' => time() * 1000,
        ];

        $httpClient = new HttpClient($this->baseURL,
            new SignatureService($this->privateKey),
            new EncryptionService($this->publicKey, $this->orgKeyId)
        );

        $path = 'req/api/self-tsp/async/normal-enqueue';

        return $httpClient->sendPackets($path, [$packet], $headers, true, true);
    }

    public function getToken()
    {
        $signatureService = new SignatureService($this->privateKey);

        $encryptionService = new EncryptionService($this->orgKeyId, null);

        $httpClient = new HttpClient($this->baseURL, $signatureService, $encryptionService);

        $api = new Api($this->username, $httpClient);

        return $api->getToken();
    }

    public function generateTaxId(DateTime $invoiceCreatedAt, $internalInvoiceId): string
    {
        $invoiceIdService = new InvoiceIdService($this->username);

        return $invoiceIdService->generateInvoiceId($invoiceCreatedAt, $internalInvoiceId);
    }

    public function inquiryByReferenceNumber(string $referenceNumber)
    {
        $signatureService = new SignatureService($this->privateKey);
        $encryptionService = new EncryptionService($this->orgKeyId, null);
        $httpClient = new HttpClient($this->baseURL, $signatureService, $encryptionService);
        $api = new Api($this->username, $httpClient);
        $api->setToken($this->token);
        return $api->inquiryByReferenceNumber($referenceNumber);
    }

    public function getEconomicCodeInformation(string $taxID)
    {
        $signatureService = new SignatureService($this->privateKey);
        $encryptionService = new EncryptionService($this->orgKeyId, null);
        $httpClient = new HttpClient($this->baseURL, $signatureService, $encryptionService);
        $api = new Api($this->username, $httpClient);
        $api->setToken($this->token);
        return $api->getEconomicCodeInformation($taxID);
    }

    public function getFiscalInfo()
    {
        $signatureService = new SignatureService($this->privateKey);
        $encryptionService = new EncryptionService($this->orgKeyId, null);
        $httpClient = new HttpClient($this->baseURL, $signatureService, $encryptionService);
        $api = new Api($this->username, $httpClient);
        $api->setToken($this->token);
        return $api->getFiscalInfo();
    }
}
