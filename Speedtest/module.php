<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

if (@constant('IPS_BASE') == null) {
    // --- BASE MESSAGE
    define('IPS_BASE', 10000);							// Base Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 1);			// Pre Shutdown Message, Runlevel UNINIT Follows
    define('IPS_KERNELSTARTED', IPS_BASE + 2);			// Post Ready Message
    // --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);		// Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);			// Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);			// Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);			// Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);			// Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);		// Uninit Complete, Destroying Kernel Inteface
    // --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);			// Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);			// Normal Message
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);			// Success Message
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);			// Notiy about Changes
    define('KL_WARNING', IPS_LOGMESSAGE + 4);			// Warnings
    define('KL_ERROR', IPS_LOGMESSAGE + 5);				// Error Message
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);				// Debug Informations + Script Results
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);			// User Message
}

if (!defined('IPS_BOOLEAN')) {
    define('IPS_BOOLEAN', 0);
}
if (!defined('IPS_INTEGER')) {
    define('IPS_INTEGER', 1);
}
if (!defined('IPS_FLOAT')) {
    define('IPS_FLOAT', 2);
}
if (!defined('IPS_STRING')) {
    define('IPS_STRING', 3);
}

class Speedtest extends IPSModule
{
    use SpeedtestCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('update_interval', '0');

        $this->RegisterTimer('UpdateData', 0, 'Speedtest_UpdateData(' . $this->InstanceID . ');');

        $this->CreateVarProfile('Speedtest.ms', IPS_FLOAT, ' ms', 0, 0, 0, 0, '');
        $this->CreateVarProfile('Speedtest.MBits', IPS_FLOAT, ' MBit/s', 0, 0, 0, 1, '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;
        $this->MaintainVariable('ISP', $this->Translate('ISP'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('IP', $this->Translate('external IP'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('Host', $this->Translate('Test-Host'), IPS_STRING, '', $vpos++, true);
        $this->MaintainVariable('Ping', $this->Translate('Ping'), IPS_FLOAT, 'Speedtest.ms', $vpos++, true);
        $this->MaintainVariable('Upload', $this->Translate('Upload'), IPS_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('Download', $this->Translate('Download'), IPS_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('LastStatus', $this->Translate('Last status'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->SetStatus(102);

        $this->SetUpdateInterval();
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        $time_start = microtime(true);
        $data = exec('speedtest-cli --secure --json 2>&1', $output, $exitcode);
        $duration = floor((microtime(true) - $time_start) * 100) / 100;

        if ($exitcode) {
            $ok = false;
            $err = $data;
        } else {
            $ok = true;
            $err = '';
        }

        if (preg_match('/speedtest-cli: error:\s(.*?)$/', $data, $r)) {
            $err = $r[1];
            $ok = false;
        }

        $this->SendDebug(__FUNCTION__, 'duration=' . $duration . ', exitcode=' . $exitcode . ', ok=' . ($ok ? 'true' : 'false') . ', err=' . $err, 0);

        $isp = '';
        $ip = '';
        $sponsor = '';
        $ping = '';
        $download = '';
        $upload = '';

        if ($ok) {
            $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
            $jdata = json_decode($data, true);
            if ($jdata == '') {
                $ok = false;
                $err = 'malformed data';
            } else {
                $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
                if (isset($jdata['client']['isp'])) {
                    $isp = $jdata['client']['isp'];
                }
                if (isset($jdata['client']['ip'])) {
                    $ip = $jdata['client']['ip'];
                }
                if (isset($jdata['server']['sponsor'])) {
                    $sponsor = $jdata['server']['sponsor'];
                }
                if (isset($jdata['ping'])) {
                    $ping = $jdata['ping'];
                }
                if (isset($jdata['download'])) {
                    $download = floor($jdata['download'] / 1024 / 1024 * 10) / 10;
                }
                if (isset($jdata['upload'])) {
                    $upload = floor($jdata['upload'] / 1024 / 1024 * 10) / 10;
                }
                $this->SendDebug(__FUNCTION__, ' ... isp=' . $isp . ' ip=' . $ip . ' sponsor=' . $sponsor . ' ping=' . $ping . ' download=' . $download . ' upload=' . $upload, 0);
            }
        }

        if ($ok) {
            $this->SetValue('ISP', $isp);
            $this->SetValue('IP', $ip);
            $this->SetValue('Host', $sponsor);
            $this->SetValue('Ping', $ping);
            $this->SetValue('Upload', $upload);
            $this->SetValue('Download', $download);
        } else {
            $msg = 'failed: exitcode=' . $exitcode . ', err=' . $err;
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__ . ': ' . $msg, 0);
        }
    }
}
