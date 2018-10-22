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

if (!defined('vtBoolean')) {
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
    define('vtArray', 8);
    define('vtObject', 9);
}

class Speedtest extends IPSModule
{
    use SpeedtestCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('update_interval', '0');
        $this->RegisterPropertyInteger('preferred_server', '0');
        $this->RegisterPropertyString('exclude_server', '');
        $this->RegisterPropertyBoolean('no_pre_allocate', true);

        $this->RegisterTimer('UpdateData', 0, 'Speedtest_UpdateData(' . $this->InstanceID . ');');

        $this->CreateVarProfile('Speedtest.ms', vtFloat, ' ms', 0, 0, 0, 0, '');
        $this->CreateVarProfile('Speedtest.MBits', vtFloat, ' MBit/s', 0, 0, 0, 1, '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;
        $this->MaintainVariable('ISP', $this->Translate('Internet-Provider'), vtString, '', $vpos++, true);
        $this->MaintainVariable('IP', $this->Translate('external IP'), vtString, '', $vpos++, true);
        $this->MaintainVariable('Server', $this->Translate('Server'), vtString, '', $vpos++, true);
        $this->MaintainVariable('Ping', $this->Translate('Ping'), vtFloat, 'Speedtest.ms', $vpos++, true);
        $this->MaintainVariable('Upload', $this->Translate('Upload'), vtFloat, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('Download', $this->Translate('Download'), vtFloat, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('LastTest', $this->Translate('Last test'), vtInteger, '~UnixTimestamp', $vpos++, true);

        $this->SetStatus(102);

        $this->SetUpdateInterval();
    }

    public function GetConfigurationForm()
    {
        $options = [];
        $options[] = ['label' => $this->Translate('automatically select'), 'value' => 0];

        $data = exec('speedtest-cli --list 2>&1', $output, $exitcode);
        $n = 0;
        foreach ($output as $line) {
            if (preg_match('/[ ]*([0-9]*)\)\s([^[]*)/', $line, $r)) {
                if ($r[1] > 0) {
                    $options[] = ['label' => $r[2], 'value' => $r[1]];
                    if ($n++ == 100) {
                        break;
                    }
                }
            }
        }

        $formElements = [];
        $formElements[] = ['type' => 'Select', 'name' => 'preferred_server', 'caption' => 'Preferred server', 'options' => $options];
        $formElements[] = ['type' => 'Label', 'label' => 'Excluded server (comma-separated)'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'exclude_server', 'caption' => 'List'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'no_pre_allocate', 'caption' => 'Set option --no_pre_allocate'];
        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X minutes'];
        $formElements[] = ['type' => 'IntervalBox', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Label', 'label' => 'Updating the data takes up to 1 minute'];
        $formActions[] = ['type' => 'Button', 'label' => 'Update data', 'onClick' => 'Speedtest_UpdateData($id);'];

        $formStatus = [];
        $formStatus[] = ['code' => '101', 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => '102', 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => '104', 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        $preferred_server = $this->ReadPropertyInteger('preferred_server');
        $exclude_server = $this->ReadPropertyString('exclude_server');

        $this->PerformTest($preferred_server, $exclude_server);
    }

    public function PerformTest(int $preferred_server, string $exclude_server)
    {
        $cmd = 'speedtest-cli --json';

        // use https instead of http
        // $cmd .= ' --secure';

        // Do not pre allocate upload data. Pre allocation is
        // enabled by default to improve upload performance. To
        // support systems with insufficient memory, use this
        // option to avoid a MemoryError
        $no_pre_allocate = $this->ReadPropertyBoolean('no_pre_allocate');
        if ($no_pre_allocate) {
            $cmd .= ' --no-pre-allocate';
        }

        // Specify a server ID to test against. Can be supplied
        // multiple times
        if ($preferred_server > 0) {
            $cmd .= ' --server=' . $preferred_server;
        }

        // Exclude a server from selection. Can be supplied
        // multiple times
        if ($exclude_server != '') {
            $serverV = explode(',', $exclude_server);
            foreach ($serverV as $server) {
                $cmd .= ' --exclude=' . $server;
            }
        }

        $cmd .= ' 2>&1';

        $this->SendDebug(__FUNCTION__, 'cmd="' . $cmd . '"', 0);

        $time_start = microtime(true);
        $data = exec($cmd, $output, $exitcode);
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

        $this->SendDebug(__FUNCTION__, 'duration=' . $duration . ', exitcode=' . $exitcode . ', status=' . ($ok ? 'ok' : 'fail') . ', err=' . $err, 0);

        $isp = '';
        $ip = '';
        $sponsor = '';
        $id = '';
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
                if (isset($jdata['server']['id'])) {
                    $id = $jdata['server']['id'];
                }
                if (isset($jdata['ping'])) {
                    $ping = $jdata['ping'];
                }
                if (isset($jdata['download'])) {
                    // Umrechnung von Bit auf MBit mit 2 Nachkommastellen
                    $download = floor($jdata['download'] / (1024 * 1024) * 10000) / 10000;
                }
                if (isset($jdata['upload'])) {
                    // Umrechnung von Bit auf MBit mit 2 Nachkommastellen
                    $upload = floor($jdata['upload'] / (1024 * 1024) * 10000) / 10000;
                }
                $this->SendDebug(__FUNCTION__, ' ... isp=' . $isp . ', ip=' . $ip . ', sponsor=' . $sponsor . ', id=' . $id . ', ping=' . $ping . ', download=' . $download . ', upload=' . $upload, 0);
            }
        }

        if ($ok) {
            IPS_LogMessage(__CLASS__ . '::' . __FUNCTION__, 'server=' . $id . ') ' . $sponsor . ', duration=' . $duration . ', status=' . ($ok ? 'ok' : 'fail'));
            $this->SetValue('ISP', $isp);
            $this->SetValue('IP', $ip);
            $this->SetValue('Server', $sponsor);
            $this->SetValue('Ping', $ping);
            $this->SetValue('Upload', $upload);
            $this->SetValue('Download', $download);
        } else {
            $msg = 'failed: exitcode=' . $exitcode . ', err=' . $err;
            $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': ' . $msg, KL_WARNING);
            $this->SendDebug(__FUNCTION__, $msg, 0);
        }

        $this->SetValue('LastTest', time());
    }
}
