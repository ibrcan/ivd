<?php

namespace ibrcan\ivd;
use Exception;
use ibrcan\ivd\Exceptions\ApiException;
use ibrcan\ivd\Exceptions\NullDataException;
use ibrcan\ivd\Exceptions\TestEnvironmentException;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
class Interaktif
{
    /**
     * Api Urls
     */
    const BASE_URL = "https://ivd.gib.gov.tr/";

    /**
     * Api Paths
     */
    const DISPATCH_PATH = "/tvd_server/dispatch";
    const TOKEN_PATH = "/tvd_server/assos-login";
    const REFERRER_PATH = "/tvd_side/main.jsp";

    /**
     * Username field for auth
     *
     * @var string
     */
    protected $username;

    protected $imageKod="YBG5G9";
    protected $imageID="34pcejkwqg1v1k00";

    /**
     * Password field for auth
     *
     * @var string
     */
    protected $password;


    /**
     * Session Token
     *
     * @var string
     */
    protected $token;

    /**
     * Referrer variable
     *
     * @var string
     */
    protected $referrer;

    /**
     * Debug mode
     *
     * @var boolean
     */
    protected $debugMode = false;

    /**
     * Invoices
     *
     * @var array ibrcan\ivd\Models\Invoice
     */
    protected $invoices = [];

    /**
     * User Informations
     *
     * @var ibrcan\ivd\Models\UserInformations
     */
    protected $userInformations;


    /**
     * Operation identifier for SMS Verification
     *
     * @var string
     */
    protected $oid;

    /**
     * Base headers
     *
     * @var array
     */
    protected $headers = [
        "accept" => "*/*",
        "accept-language" => "tr,en-US;q=0.9,en;q=0.8",
        "cache-control" => "no-cache",
        "content-type" => "application/x-www-form-urlencoded;charset=UTF-8",
        "pragma" => "no-cache",
        "sec-fetch-mode" => "cors",
        "sec-fetch-site" => "same-origin",
        "User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.67 Safari/537.36", // Dummy UA
    ];

    public function __construct()
    {
        $this->referrer = $this->getBaseUrl() . self::REFERRER_PATH;
        $this->headers["referrer"] = $this->referrer;

        $this->client = new Client($this->headers);
    }

    /**
     * Setter function for username
     *
     * @param string $username
     * @return ibrcan\ivd\InvoiceManager
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Set a debug mode
     *
     * @param boolean $status
     * @return ibrcan\ivd\InvoiceManager
     */
    public function setDebugMode($status)
    {
        $this->debugMode = $status;
        return $this;
    }

    /**
     * Setter function for password
     *
     * @param string $password
     * @return ibrcan\ivd\InvoiceManager
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function setTestCredentials()
    {
        $response = $this->client->post($this->getBaseUrl() . "/earsiv-services/esign", [
            "form_params" => [
                "assoscmd" => "kullaniciOner",
                "rtype" => "json",
            ]
        ]);
        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        if (isset($body["userid"]) and $body["userid"] == "") {
            throw new TestEnvironmentException("eArsiv test kullanıcısı alınamadı. Lütfen daha sonra deneyin.");
        }

        $this->username = $body["userid"];
        $this->password = "1";
        return $this;
    }

    /**
     * Setter function for all credentials
     *
     * @param string $username
     * @param string $password
     * @return ibrcan\ivd\InvoiceManager
     */
    public function setCredentials($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Setter function for token
     *
     * @param string $token
     * @return ibrcan\ivd\InvoiceManager
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $token;
    }

    /**
     * Getter function for token
     *
     * @param string $token
     * @return ibrcan\ivd\InvoiceManager
     */
    public function getToken($token)
    {
        return $this->token;
    }

    /**
     * Connect with credentials
     *
     * @return ibrcan\ivd\InvoiceManager
     */
    public function connect()
    {
        $this->getTokenFromApi();
        return $this;
    }

    /**
     * Get all credentials as an array
     *
     * @return array
     */
    public function getCredentials()
    {
        return [
            $this->username,
            $this->password
        ];
    }

    /**
     * Get base url
     *
     * @return string
     */
    public function getBaseUrl()
    {
        if ($this->debugMode) {
            return self::TEST_URL;
        }
        return self::BASE_URL;
    }

    /**
     * Send request, json decode and return response
     *
     * @param string $url
     * @param array $parameters
     * @param array $headers
     * @return array
     */
    private function sendRequestAndGetBody($url, $parameters, $headers = null)
    {
        $response = $this->client->post($this->getBaseUrl() . "$url", [
            "headers" => $headers ? $headers : $this->headers,
            "form_params" => $parameters
        ]);

        $body = json_decode($response->getBody(), true);
        return $body;
    }

    /**
     * Get auth token
     *
     * @return string
     */
    public function getTokenFromApi()
    {
        $parameters = [
            "assoscmd" => "multilogin",
            "rtype" => "json",
            "userid" => $this->username,
            "sifre" => $this->password,
            "dk" => $this->imageKod,
            "parola" => "maliye",
            "controlCaptcha" =>true,
            "imageID" =>$this->imageID
        ];
        $body = $this->sendRequestAndGetBody(self::TOKEN_PATH, $parameters, []);
        $this->checkError($body);
        return $this->token = $body["token"];
    }

    /**
     * Logout from API
     *
     * @return string
     */
    public function logOutFromAPI()
    {
        $parameters = [
            "cmd" => "kullaniciBilgileriService_logout",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "PG_MAIN_DYNAMIC",
            "token" => $this->token,
            "jp" => '{}'
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters, []);
        $this->checkError($body);
        $this->token = null;
        return true;
    }

    /**
     * Check error, if exist throw it!
     *
     * @param array $jsonData
     * @return void
     */
    private function checkError($jsonData)
    {
        if (isset($jsonData["error"])) {
          if (isset($jsonData["messages"][0]["text"])) {
            throw new ApiException("Sunucu taraflı bir hata oluştu!  Hata Mesajı:".$jsonData["messages"][0]["text"], 0, null, $jsonData);
          } else {
            throw new ApiException("Sunucu taraflı bir hata oluştu!", 0, null, $jsonData);
          }
        }
    }


    public function getCompanyInfo($taxNr)
    {
        $parameters = [
            "cmd" => "SICIL_VEYA_MERNISTEN_BILGILERI_GETIR",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_BASITFATURA",
            "token" => $this->token,
            "jp" => '{"vknTcknn":"' . $taxNr . '"}'
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        return $body;
    }


     public function getInvoicesFromAPI($startDate, $endDate)
     {
         $parameters = [
             "cmd" => "EARSIV_PORTAL_TASLAKLARI_GETIR",
             "callid" => Uuid::uuid1()->toString(),
             "pageName" => "RG_BASITTASLAKLAR",
             "token" => $this->token,
             "jp" => '{"baslangic":"' . $startDate . '","bitis":"' . $endDate . '","hangiTip":"5000/30000", "table":[]}'
         ];

         $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
         $this->checkError($body);

         // Array tipinden verilen tarih aralığında yer alan faturalar dönüyor
         $this->invoices = $body['data'];

         return $body;
     }
     public function getInvoicesMeFromAPI($startDate, $endDate)
     {
         $parameters = [
             "cmd" => "EFaturaIslemleri_eFaturaGoruntuleSorgula",
             "callid" => Uuid::uuid1()->toString(),
             "pageName" => "P_EFATURA",
             "token" => $this->token,
             "jp" => '{"faturaTarihBas":"' . $startDate . '","textBox":"","faturaTarihSon":"' . $endDate . '"}'
         ];
         //print_r($parameters);

         $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
         //print_r($body);
         $this->checkError($body);

         // Array tipinden verilen tarih aralığında yer alan faturalar dönüyor
         $this->invoices = $body['data'];

         return $body;
     }

    /**
     * Get main three menu from api
     *
     * @return array
     */
    public function getMainTreeMenuFromAPI()
    {

        $headers = [
            "referrer" => $this->referrer
        ];

        $parameters = [
            "cmd" => "getUserMenu",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "MAINTREEMENU",
            "token" => $this->token,
            "jp" => '{"ANONIM_LOGIN":"1"}'
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters, $headers);
        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Create draft basic invoice
     *
     * @param Invoice $invoice
     * @return furkankadioglu\eFatura\Models\Invoice
     */
    public function createDraftBasicInvoice(Invoice $invoice = null)
    {
        if ($invoice != null) {
            $this->invoice = $invoice;
        }

        if ($this->invoice == null) {
            throw new NullDataException("Invoice variable not exist");
        }

        $parameters = [
            "cmd" => "EARSIV_PORTAL_FATURA_OLUSTUR",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_BASITFATURA",
            "token" => $this->token,
            "jp" => "" . json_encode($this->invoice->export()) . ""
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        if ($body["data"] != "Faturanız başarıyla oluşturulmuştur. Düzenlenen Belgeler menüsünden faturanıza ulaşabilirsiniz.") {
            throw new ApiException("Fatura oluşturulamadı.", 0, null, $body);
        }

        return $this;
    }

    /**
     * Get html invoice
     *
     * @param Invoice $invoice
     * @return void
     */
    public function getInvoiceHTML(Invoice $invoice = null, $signed = true)
    {
        if ($invoice != null) {
            $this->invoice = $invoice;
        }

        if ($this->invoice == null) {
            throw new NullDataException("Invoice variable not exist");
        }

        $data = [
            "ettn" => $this->invoice->getUuid(),
            "onayDurumu" => $signed ? "Onaylandı" : "Onaylanmadı"
        ];

        $parameters = [
            "cmd" => "EARSIV_PORTAL_FATURA_GOSTER",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_TASLAKLAR",
            "token" => $this->token,
            "jp" => "" . json_encode($data) . "",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        return $body["data"];
    }

    /**
     * PDF Export
     *
     * @param Invoice $invoice
     * @param boolean $signed
     * @return Mpdf\Mpdf
     */
    public function getInvoicePDF(Invoice $invoice = null, $signed = true)
    {
        $data = $this->getInvoiceHTML($invoice, $signed);
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($data);
        return $mpdf->Output();
    }

    /**
     * Cancel an invoice
     *
     * @param Invoice $invoice
     * @return boolean
     */
    public function cancelInvoice(Invoice $invoice = null, $reason = "Yanlış İşlem")
    {
        if ($invoice != null) {
            $this->invoice = $invoice;
        }

        if ($this->invoice == null) {
            throw new NullDataException("Invoice variable not exist");
        }

        $data = [
            "silinecekler" => [$this->invoice->getSummary()],
            "aciklama" => $reason
        ];


        $parameters = [
            "cmd" => "EARSIV_PORTAL_FATURA_SIL",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_BASITTASLAKLAR",
            "token" => $this->token,
            "jp" => "" . json_encode($data) . "",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        if (strpos($body["data"], " fatura başarıyla silindi.") == false) {
            throw new ApiException("Fatura iptal edilemedi.", 0, null, $body);
        }

        return true;
    }

    /**
     * Get an invoice from API
     *
     * @param Invoice $invoice
     * @return array
     */
    public function getInvoiceFromAPI(Invoice $invoice = null)
    {
        if ($invoice != null) {
            $this->invoice = $invoice;
        }

        if ($this->invoice == null) {
            throw new NullDataException("Invoice variable not exist");
        }

        $data = [
            "ettn" => $this->invoice->getUuid()
        ];

        $parameters = [
            "cmd" => "EARSIV_PORTAL_FATURA_GETIR",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_BASITFATURA",
            "token" => $this->token,
            "jp" => "" . json_encode($data) . "",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);

        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Get download url
     *
     * @param Invoice $invoice
     * @param boolean $signed
     * @return string
     */
    public function getDownloadURL(Invoice $invoice = null, $signed = true)
    {
        if ($invoice != null) {
            $this->invoice = $invoice;
        }

        if ($this->invoice == null) {
            throw new NullDataException("Invoice variable not exist");
        }

        $signed = $signed ? "Onaylandı" : "Onaylanmadı";

        return $this->getBaseUrl() . "/earsiv-services/download?token={$this->token}&ettn={$this->invoice->getUuid()}&belgeTip=FATURA&onayDurumu={$signed}&cmd=EARSIV_PORTAL_BELGE_INDIR";
    }

    /**
     * Set invoice manager user informations
     *
     * @param furkankadioglu\eFatura\Models\UserInformations $userInformations
     * @return furkankadioglu\eFatura\Models\Invoice
     */
    public function setUserInformations(UserInformations $userInformations)
    {
        $this->userInformations = $userInformations;
        return $this;
    }

    /**
     * Get invoice manager user informations
     *
     * @return furkankadioglu\eFatura\Models\UserInformations
     */
    public function getUserInformations()
    {
        return $this->userInformations;
    }

    /**
     * Get user informations data
     *
     * @return furkankadioglu\eFatura\Models\UserInformations
     */
    public function getUserInformationsData()
    {
        $parameters = [
            "cmd" => "EARSIV_PORTAL_KULLANICI_BILGILERI_GETIR",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_KULLANICI",
            "token" => $this->token,
            "jp" => "{}",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        $userInformations = new UserInformations($body["data"]);
        return $this->userInformations = $userInformations;
    }

    /**
     * Get Invoices from API
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $ettn
     * @return array
     */
    public function getEttnInvoiceFromAPIArray($startDate, $endDate, $ettn)
    {
        $parameters = [
            "cmd" => "EARSIV_PORTAL_TASLAKLARI_GETIR",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_BASITTASLAKLAR",
            "token" => $this->token,
            "jp" => '{"baslangic":"' . $startDate . '","bitis":"' . $endDate . '","hangiTip":"5000/30000", "table":[]}'
        ];
        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);
        $data = $body['data'];
        $dataFiltered = array();
        foreach($data as $item){
	        if($item["onayDurumu"] == "Onaylanmadı" AND in_array($item["ettn"], $ettn)){
		        array_push($dataFiltered, $item);
	        }
        }
        $this->invoices = $dataFiltered;
        return $dataFiltered;
    }

    /**
     * Send user informations data
     *
     * @param Invoice $invoice
     * @return array
     */
    public function sendUserInformationsData(UserInformations $userInformations = null)
    {
        if ($userInformations != null) {
            $this->userInformations = $userInformations;
        }

        if ($this->userInformations == null) {
            throw new NullDataException("User informations data not exist");
        }

        $parameters = [
            "cmd" => "EARSIV_PORTAL_KULLANICI_BILGILERI_KAYDET",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_KULLANICI",
            "token" => $this->token,
            "jp" => "" . json_encode($this->userInformations->export()) . "",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Send user informations data
     *
     * @param string $phoneNumber
     * @return array
     */
    public function sendSMSVerification($phoneNumber)
    {
        $data = [
            "CEPTEL" => $phoneNumber,
            "KCEPTEL" => false,
            "TIP" => ""
        ];

        $parameters = [
            "cmd" => "EARSIV_PORTAL_SMSSIFRE_GONDER",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_SMSONAY",
            "token" => $this->token,
            "jp" => "" . json_encode($data) . "",
        ];
        print_r($parameters);
        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        $this->oid = $body["data"]["oid"];

        return $this->oid;
    }

    /**
     * Send user informations data
     *
     * @param string $phoneNumber
     * @return array
     */
    public function verifySMSCode($code, $operationId)
    {
        $data = [
            "SIFRE" => $code,
            "OID" => $operationId,
            'OPR' => 1,
            'DATA' => $this->invoices,
        ];

        $parameters = [
            "cmd" => "0lhozfib5410mp",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "RG_SMSONAY",
            "token" => $this->token,
            "jp" => "" . json_encode($data) . "",
        ];

        $body = $this->sendRequestAndGetBody(self::DISPATCH_PATH, $parameters);
        $this->checkError($body);

        if(!isset($body["data"]["sonuc"]))
        {
            return false;
        }

        if($body["data"]["sonuc"] == 0)
        {
            return false;
        }

        return true;
    }
}
