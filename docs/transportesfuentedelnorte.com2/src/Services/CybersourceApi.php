<?php

namespace App\Services;

use App\Entity\ClienteReservacion;
use App\Entity\Reservacion;
use App\Entity\Tarjeta;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class CybersourceApi {
    public const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    public const AUTHENTICATION_SUCCESSFUL = 'AUTHENTICATION_SUCCESSFUL';
    public const PENDING_AUTHENTICATION = 'PENDING_AUTHENTICATION';
    public const AUTHORIZED_RISK_DECLINED = 'AUTHORIZED_RISK_DECLINED';
    public const AUTHORIZED = 'AUTHORIZED';
    public const DECLINED = 'DECLINED';

    private ?ClienteReservacion $cliente = null;
    private array|null $pago_datos = [];

    private $paymentInformation;
    private $clientReferenceInformation;
    private $orderInformation;

    public function __construct(private Reservacion $reservacion, private HttpClientInterface $cybersourceClient, private RequestStack $requestStack, private EntityManagerInterface $entityManagerInterface, private ServerSentEvent $serverSentEvent,  private $cybersource_base_uri, private $endpoints, private $credenciales) {
        $this->init();
    }

    public function init() {
        $request = $this->requestStack->getCurrentRequest();
        $this->cliente = $this->reservacion->getCliente();

        if ($this->pago_datos = $request->getSession()->get('pago_datos')) {
            $this->setData();
        }
    }

    public function request($endpoint_key, $data = null, $endpointOverride = null) {
        $endpoint = $endpointOverride ?? $this->endpoints[$endpoint_key];
        $request_host = u($this->cybersource_base_uri)->replace('https://', '');
        $empresas = $this->credenciales;
        $empresa = $this->reservacion->getEmpresaFactura()->getSlug();
        $merchant_id = $empresas[$empresa]['CYBERSOURCE_MERCHANT_ID'];
        $merchant_key_id = $empresas[$empresa]['CYBERSOURCE_MERCHANT_KEY_ID'];
        $merchant_secret_key = $empresas[$empresa]['CYBERSOURCE_MERCHANT_SECRET_KEY'];


        $resource = mb_convert_encoding($endpoint, 'UTF-8');

        $date = date('D, d M Y G:i:s ') . 'GMT';

        $signatureString = '';

        $headerParams = [];
        $headers = [];

        $headerParams['Accept'] = 'application/hal+json;charset=utf-8';
        $headerParams['Content-Type'] = 'application/json;charset=utf-8';

        foreach ($headerParams as $key => $val) {
            $headers[] = "{$key}: {$val}";
        }

        $digest = '';

        $method = 'post';

        if (!$data) { // Get method
            $signatureString = 'host: ' .  $request_host . "\ndate: " . $date . "\nrequest-target: " . $method . ' ' . $resource . "\nv-c-merchant-id: " . $merchant_id;
            $headerString = 'host date request-target v-c-merchant-id';
        } else { // Post method
            // Get digest data
            $payload = json_encode($data);

            $digest = \base64_encode(
                hash(
                    'sha256',
                    mb_convert_encoding($payload, 'UTF-8'),
                    true
                )
            );


            $signatureString = 'host: ' . $request_host . "\ndate: " . $date . "\nrequest-target: " . $method . ' ' . $resource . "\ndigest: SHA-256=" . $digest . "\nv-c-merchant-id: " . $merchant_id;
            $headerString = 'host date request-target digest v-c-merchant-id';
        }

        $signatureByteString =     mb_convert_encoding($signatureString, 'UTF-8');
        $decodeKey = base64_decode($merchant_secret_key);
        $signature = base64_encode(hash_hmac('sha256', $signatureByteString, $decodeKey, true));
        $signatureHeader = [
            'keyid="' . $merchant_key_id . '"',
            'algorithm="HmacSHA256"',
            'headers="' . $headerString . '"',
            'signature="' . $signature . '"',
        ];

        $signatureToken = 'Signature:' . implode(', ', $signatureHeader);

        $host = 'Host:' . $request_host;
        $vcMerchant = 'v-c-merchant-id:' . $merchant_id;
        $authHeaders = [
            $vcMerchant,
            $signatureToken,
            $host,
            'Date:' . $date,
        ];

        if ($data) {
            $digestArray = ['Digest: SHA-256=' . $digest];
            $authHeaders = array_merge($authHeaders, $digestArray);
        }

        $headerParams = array_merge($headers, $authHeaders);

        try {

            $response = $this->cybersourceClient->request(
                'POST',
                $endpoint,
                [
                    'headers' => $headerParams,
                    'body' => $payload,
                    'timeout' => 10,
                ]
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode <= 299) {
                $responseArray = $response->toArray();

                error_log(\sprintf(
                    '[CyberSource | Visa] OK %s -> HTTP %s | %s',
                    $endpoint,
                    $statusCode,
                    \json_encode($responseArray)
                ));

                return $responseArray;
            }

            error_log(\sprintf(
                '[CyberSource | Visa] ERROR %s -> HTTP %s | respuesta: %s',
                $endpoint,
                $statusCode,
                \mb_substr($response->getContent(false), 0, 2000)
            ));

            return $statusCode;
        } catch (\Throwable $th) {
            // Incluye TransportExceptionInterface y DecodingExceptionInterface (JSON inválido):
            // ninguna excepción debe escapar de la frontera con la API de Visa.
            error_log(\sprintf(
                '[CyberSource | Visa] EXCEPCION %s -> %s',
                $endpoint,
                $th->getMessage()
            ));

            return ['error' => $th->getMessage()];
        }
    }

    public function setData() {
        if ($this->pago_datos) {
            $this->paymentInformation = [
                'paymentInformation' => [
                    'card' => [
                        'type' => $this->entityManagerInterface->getRepository(Tarjeta::class)->find($this->pago_datos['tarjeta'])->getCodigo(),
                        'expirationMonth' => $this->pago_datos['expira_mes'],
                        'expirationYear' => $this->pago_datos['expira_year'],
                        'number' => preg_replace('/\s+/', '', $this->pago_datos['numero']),
                        'securityCode' => $this->pago_datos['codigo_seguridad'],
                    ],
                ],
            ];
        }
        $this->clientReferenceInformation = [
            'clientReferenceInformation' => [
                'code' => $this->reservacion->getId(),
            ],
        ];

        if ($this->cliente) {
            if ($this->cliente->getPais()->getId() == 90) {
                $code = "GT-" . $this->cliente->getProvincia()->getIso2();
            } else {
                $code = $this->cliente->getProvincia()->getIso2();
            }
            $this->orderInformation = [
                'orderInformation' => [
                    'amountDetails' => [
                        'currency' => $this->reservacion->getMoneda(),
                        'totalAmount' => $this->reservacion->getPrecioCobrar()
                        // \in_array(
                        //     $this->reservacion->getCliente()->getEmail(),
                        //     [
                        //         'danilop471@gmail.com',
                        //         'alcidesrh@gmail.com'
                        //     ]
                        // ) ? 1 : $this->reservacion->getPrecioCobrar(),
                    ],
                    'billTo' => [
                        'address1' => $this->cliente->getDireccion(),
                        'locality' => $this->cliente->getCiudad()->getName(),
                        'country' => $this->cliente->getPais()->getIso2(),
                        'firstName' => $this->cliente->getNombre(),
                        'lastName' => $this->cliente->getApellido(),
                        'email' => $this->cliente->getEmail(),
                        'administrativeArea' => $code,
                    ],
                ],
            ];

            if ('US' == $this->cliente->getPais()->getIso2() || 'CA' == $this->cliente->getPais()->getIso2()) {
                $this->orderInformation['orderInformation']['billTo']['postalCode'] = $this->cliente->getCodigoPostal();
            }
            if ($telofono = $this->cliente->getTelefono()) {
                $this->orderInformation['orderInformation']['billTo']['telefono'] = $telofono;
            }
        }
    }

    private function deviceInformation(): array {
        $info = [
            'fingerprintSessionId' => $this->requestStack->getSession()->get('uuid'),
        ];

        // Campos de respaldo documentados de Device Information (solo los disponibles
        // del lado servidor; el resto —screen/color/timezone/java/enabled— se pueden
        // agregar recogiéndolos vía JS en pago_datos).
        $serverHeaders = [
            'HTTP_USER_AGENT' => 'userAgentBrowserValue',
            'HTTP_ACCEPT' => 'httpAcceptBrowserValue',
            'HTTP_ACCEPT_LANGUAGE' => 'httpBrowserLanguage',
        ];

        foreach ($serverHeaders as $serverKey => $field) {
            if ($value = $this->requestStack->getCurrentRequest()?->server->get($serverKey)) {
                $info[$field] = $value;
            }
        }

        return $info;
    }

    public function payerAuthenticationSetupService() {

        if ($this->reservacion->getTransaccionId()) {
            return false;
        }
        $response = $this->request('authentication_1__setup_service', [
            ...$this->clientReferenceInformation,
            ...$this->paymentInformation,
        ]);

        $this->reservacion->setStatusCybersources(__FUNCTION__ .
            (is_array($response) && isset($response['status'])
                ? $response['status']
                : ': Fallido código: ' . (\is_scalar($response) ? $response : 'respuesta vacia')));

        return $response;
    }

    public function payerAuthenticationCheckEnrollmentService($referenceId, $returnUrl) {

        if ($this->reservacion->getTransaccionId()) {
            return false;
        }

        $data = [
            ...$this->clientReferenceInformation,
            ...$this->orderInformation,
            ...$this->paymentInformation,
            ...[
                'consumerAuthenticationInformation' => [
                    'returnUrl' => $returnUrl,
                    'referenceId' => $referenceId,
                ],
                'processingInformation' => [
                    'actionList' => [
                        0 => 'CONSUMER_AUTHENTICATION',
                    ],
                    'capture' => true,
                ],
                'deviceInformation' => $this->deviceInformation(),
            ],
        ];

        $response = $this->request('payment', $data);

        $this->reservacion->setStatusCybersources(__FUNCTION__ .
            (is_array($response) && isset($response['status'])
                ? ': ' . $response['status']
                : ': Fallido código: ' . (\is_scalar($response) ? $response : 'respuesta vacia')));

        $this->entityManagerInterface->flush();

        return $response;
    }

    public function payerAuthenticationValidationService($authenticationTransactionId) {
        if ($this->reservacion->getTransaccionId()) {
            return false;
        }

        $data = [
            ...$this->clientReferenceInformation,
            ...$this->orderInformation,
            ...$this->paymentInformation,
            ...[
                'consumerAuthenticationInformation' => [
                    'authenticationTransactionId' => $authenticationTransactionId,
                ],
            ],
            'processingInformation' => [
                'actionList' => [
                    0 => 'VALIDATE_CONSUMER_AUTHENTICATION',
                ],
                'capture' => true,
            ],
            'deviceInformation' => $this->deviceInformation(),
        ];

        $response = $this->request('payment', $data);

        $this->reservacion->setStatusCybersources(__FUNCTION__ .
            (is_array($response) && isset($response['status'])
                ? ': ' . $response['status']
                : ': Fallido código: ' . (\is_scalar($response) ? $response : 'respuesta vacia')));

        $this->entityManagerInterface->flush();

        return $response;
    }

    /**
     * Revierte (void) un pago YA capturado por la API de pagos. Se usa cuando la
     * respuesta llegó AUTHORIZED/AUTHENTICATION_SUCCESSFUL (capture: true del
     * mismo request) pero la aplicación la rechaza localmente, para que el
     * cliente jamás quede cobrado sin boleto.
     */
    public function voidPayment(string $paymentId): array|int|false {

        if ('' === $paymentId) {
            return false;
        }

        $clientReferenceInformation = $this->clientReferenceInformation
            ?? ['clientReferenceInformation' => ['code' => (string) $this->reservacion->getId()]];

        $response = $this->request(
            'payment',
            $clientReferenceInformation,
            \sprintf('/pts/v2/payments/%s/voids', $paymentId)
        );

        error_log(\sprintf(
            '[CyberSource | Visa] voidPayment %s -> %s',
            $paymentId,
            \is_array($response)
                ? ($response['status'] ?? \json_encode($response))
                : (\is_scalar($response) ? (string) $response : 'respuesta vacia')
        ));

        return $response;
    }
}
