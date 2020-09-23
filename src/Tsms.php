<?php

namespace Different\Tsms;

class Tsms
{
    // Socket's port
    private $service_port;

    // Socket's IP address
    private $address;

    // Password for auth
    private $password;

    // Senders's Phone number
    private $PhoneNumberFrom;

    // Socket
    private $socket;

    // UPC start char
    private $stx;

    // UPC end char
    private $etx;

    // UPC message ID
    private $ID = 0;

    /**
     * Consructs the socket connection
     *
     * @param string $ip
     * @param int $port
     * @param string $password
     * @param string $numberfrom
     * @return bool|string
     */
    function __construct($ip, $port, $password, $numberfrom)
    {
        $this->stx = chr(2);
        $this->etx = chr(3);

        $this->address = $ip;
        $this->service_port = $port;
        $this->password = $password;
        $this->PhoneNumberFrom = $numberfrom;

        try {
            $this->_socketOpen();
            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $phoneNumber
     * @param string $message
     * @return string
     */
    public function sendSMS($phoneNumber = '', $message = '')
    {
        try {
            $AuthStr = $this->_getAuthStr();
            $AuthRequest = $this->_socketWrite($AuthStr);
            $AuthResponse = $this->_socketRead();

            $SMSStr = $this->_getSMSStr($phoneNumber, $message);
            $SMSRequest = $this->_socketWrite($SMSStr);
            $SMSResponse = $this->_socketRead();

            $this->_socketClose();

            return $SMSResponse;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @return string Auth string
     */
    private function _getAuthStr()
    {
        $UPC60_Data = '/O/60/%s/6/5/1/%s//0100////39//';

        $_passw = $this->_convto7bit($this->password);
        $UPC60 = sprintf($UPC60_Data, (string) $this->service_port, (string) $_passw);

        return $this->_createUPCStr($UPC60);
    }

    /**
     * @param string $PhoneNumberTo
     * @param string $message
     * @return string UPC SMS string
     */
    private function _getSMSStr($PhoneNumberTo = '', $message = '')
    {
        $UPC51_Data = '/O/51/%s/%s/////////////////3//%s////////1139/////';

        $_sms = $this->_strToHex($this->_convto7bit($message));
        $UPC51 = sprintf($UPC51_Data, (string) $PhoneNumberTo, (string) $this->PhoneNumberFrom, (string) $_sms);

        return $this->_createUPCStr($UPC51);
    }

    /**
     * @param string $UPCData
     * @return string UPC ready string
     */
    private function _createUPCStr($UPCData)
    {
        $Len = strlen($UPCData) + 10;
        $Result = str_pad((string) $this->ID, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $Len, 5, '0', STR_PAD_LEFT) . $UPCData;
        $Result = $Result . $this->_crc($Result);

        $this->ID++;
        if ($this->ID > 99) {
            $this->ID = 0;
        }

        return $this->stx . $Result . $this->etx;
    }

    /**
     * @param string $str
     * @param string $from_enc
     * @return string Converted string
     */
    private function _convto7bit($str, $from_enc = 'UTF-8')
    {
        setlocale(LC_ALL, 'hu_HU');
        return iconv($from_enc, 'US-ASCII//TRANSLIT', $str);
    }

    /**
     * @param string $str
     * @return string Converted string
     */
    private function _strToHex($str)
    {
        $hex = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $hex .= dechex(ord($str[$i]));
        }
        return $hex;
    }

    /**
     * @param string $str
     * @return string Calculated CRC
     */
    private function _crc($str)
    {
        $c = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $c += ord($str[$i]);
        }
        return str_pad((string) dechex($c % 256), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return bool|string
     */
    private function _socketOpen()
    {
        $this->socket = fsockopen($this->address, $this->service_port, $errno, $errstr, 30);

        if (!$this->socket) {
            throw new \Exception("Socket connection error: $errstr ($errno)");
        } else {
            return true;
        }
    }

    /**
     * @return bool
     */
    private function _socketClose()
    {
        return fclose($this->socket);
    }

    /**
     * @param string $message
     * @return bool|string
     */
    private function _socketWrite($message = '')
    {
        $sw = fwrite($this->socket, $message);
        if ($sw === false) {
            throw new \Exception("socketWrite() error");
        } else {
            return true;
        }
    }

    /**
     * @return bool|string
     */
    private function _socketRead()
    {
        return fread($this->socket, 255);
    }
}
