<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class Speedtest extends IPSModule
{
    use SpeedtestCommonLib;
    use SpeedtestLocalLib;

    public static $Mode_SpeedtestCli = 0;
    public static $Mode_Ookla = 1;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('update_interval', '0');
        $this->RegisterPropertyInteger('preferred_server', '0');
        $this->RegisterPropertyString('exclude_server', '');

        $this->RegisterPropertyInteger('program_version', 0);
        $this->RegisterPropertyBoolean('no_pre_allocate', true);

        $this->RegisterPropertyString('full_path', '');

        $this->RegisterPropertyBoolean('with_logging', false);

        $this->RegisterTimer('UpdateData', 0, 'Speedtest_UpdateData(' . $this->InstanceID . ');');

        $this->CreateVarProfile('Speedtest.ms', VARIABLETYPE_FLOAT, ' ms', 0, 0, 0, 0, '');
        $this->CreateVarProfile('Speedtest.MBits', VARIABLETYPE_FLOAT, ' MBit/s', 0, 0, 0, 1, '');
    }

    private function CheckPrerequisites()
    {
        $s = '';

        $version = $this->ReadPropertyInteger('program_version');
        $path = $this->ReadPropertyString('full_path');
        switch ($version) {
            case self::$Mode_Ookla:
                $cmd = $path != '' ? $path : 'speedtest';
                $cmd .= ' --version --accept-license --accept-gdpr';
                $prog = 'speedtest';
                break;
            case self::$Mode_SpeedtestCli:
                $cmd = $path != '' ? $path : 'speedtest-cli';
                $cmd .= ' --version';
                $prog = 'speedtest-cli';
                break;
            default:
                $s = $this->Translate('no valid program version selected');
                return $s;
        }
        $data = exec($cmd . ' 2>&1', $output, $exitcode);
        $this->SendDebug(__FUNCTION__, 'cmd=' . $cmd . ', exitcode=' . $exitcode . ', output=' . print_r($output, true), 0);
        if ($exitcode != 0) {
            $s = $this->Translate('The following system prerequisites are missing') . ': ' . $prog;
        }

        return $s;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;
        $this->MaintainVariable('ISP', $this->Translate('Internet-Provider'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('IP', $this->Translate('external IP'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('Server', $this->Translate('Server'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('Ping', $this->Translate('Ping'), VARIABLETYPE_FLOAT, 'Speedtest.ms', $vpos++, true);
        $this->MaintainVariable('Upload', $this->Translate('Upload'), VARIABLETYPE_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('Download', $this->Translate('Download'), VARIABLETYPE_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('LastTest', $this->Translate('Last test'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
        $this->SetUpdateInterval();
    }

    private function GetFormElements()
    {
        $formElements = [];

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'Select',
            'name'    => 'program_version',
            'caption' => 'Program version',
            'options' => [
                [
                    'caption' => $this->Translate('Default version of speedtest-cli'),
                    'value'   => self::$Mode_SpeedtestCli,
                ],
                [
                    'caption' => $this->Translate('Original speedtest from Ookla'),
                    'value'   => self::$Mode_Ookla,
                ],
            ]
        ];

        $options = [];
        $options[] = [
            'caption' => $this->Translate('automatically select'),
            'value'   => 0
        ];
        $path = $this->ReadPropertyString('full_path');
        $version = $this->ReadPropertyInteger('program_version');
        switch ($version) {
            case self::$Mode_SpeedtestCli:
                $cmd = $path != '' ? $path : 'speedtest-cli';
                $cmd .= ' --list';
                $data = exec($cmd . ' 2>&1', $output, $exitcode);
                $n = 0;
                foreach ($output as $line) {
                    if (preg_match('/[ ]*([0-9]*)\)\s([^[]*)/', $line, $r)) {
                        if ($r[1] > 0) {
                            $options[] = [
                                'caption' => $r[2],
                                'value'   => (int) $r[1]
                            ];
                            if ($n++ == 100) {
                                break;
                            }
                        }
                    }
                }
                break;
            case self::$Mode_Ookla:
                $cmd = $path != '' ? $path : 'speedtest';
                $cmd .= ' --servers';
                $data = exec($cmd . ' 2>&1', $output, $exitcode);
                $n = 0;
                foreach ($output as $line) {
                    if ($n++ < 4) {
                        continue;
                    }
                    if (preg_match('/[ ]*([0-9]*)\s([^[]*)/', $line, $r)) {
                        if ($r[1] > 0) {
                            $options[] = [
                                'caption' => $r[2],
                                'value'   => (int) $r[1]
                            ];
                        }
                    }
                }
                break;
        }

        $formElements[] = [
            'type'    => 'Select',
            'name'    => 'preferred_server',
            'caption' => 'Preferred server',
            'options' => $options
        ];

        if ($version == self::$Mode_SpeedtestCli) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Excluded server (comma-separated)'
            ];
            $formElements[] = [
                'type'    => 'ValidationTextBox',
                'name'    => 'exclude_server',
                'caption' => 'List'
            ];
            $formElements[] = [
                'type'    => 'CheckBox',
                'name'    => 'no_pre_allocate',
                'caption' => 'Set option --no_pre_allocate'
            ];
        }

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'Label',
                    'caption' => 'Full qualified path to testprogram, only needed if irregular installed',
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'full_path',
                    'caption' => 'Program path'
                ]
            ]
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Update data every X minutes'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'caption' => 'Minutes'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Label',
            'caption' => 'Updating the data takes up to 1 minute'
        ];
        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update data',
            'onClick' => 'Speedtest_UpdateData($id);'
        ];

        return $formActions;
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
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
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $path = $this->ReadPropertyString('full_path');
        $version = $this->ReadPropertyInteger('program_version');
        switch ($version) {
            case self::$Mode_SpeedtestCli:
                $cmd = $path != '' ? $path : 'speedtest-cli';
                $cmd .= ' --json';

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
                break;
            case self::$Mode_Ookla:
                $cmd = $path != '' ? $path : 'speedtest';
                $cmd .= ' --format=json';

                // Specify a server ID to test against.
                if ($preferred_server > 0) {
                    $cmd .= ' --server-id=' . $preferred_server;
                }
        }

        $this->SendDebug(__FUNCTION__, 'cmd="' . $cmd . '"', 0);

        $time_start = microtime(true);
        $data = exec($cmd . ' 2>&1', $output, $exitcode);
        $duration = floor((microtime(true) - $time_start) * 100) / 100;

        if ($exitcode) {
            $ok = false;
            $err = $data;
        } else {
            $ok = true;
            $err = '';
        }

        switch ($version) {
            case self::$Mode_SpeedtestCli:
                if (preg_match('/speedtest-cli: error:\s(.*?)$/', $data, $r)) {
                    $err = $r[1];
                    $ok = false;
                }
                if (preg_match('/^ERROR:\s(.*?)$/', $data, $r)) {
                    $err = $r[1];
                    $ok = false;
                }
                break;
            case self::$Mode_Ookla:
                if (preg_match('/^\[error\]\s(.*?)$/', $data, $r)) {
                    $err = $r[1];
                    $ok = false;
                }
                break;
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
                switch ($version) {
                    case self::$Mode_SpeedtestCli:
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
                        break;
                    case self::$Mode_Ookla:
                        if (isset($jdata['isp'])) {
                            $isp = $jdata['isp'];
                        }
                        if (isset($jdata['interface']['externalIp'])) {
                            $ip = $jdata['interface']['externalIp'];
                        }
                        if (isset($jdata['server']['name'])) {
                            $sponsor = $jdata['server']['name'];
                        }
                        if (isset($jdata['server']['id'])) {
                            $id = $jdata['server']['id'];
                        }
                        if (isset($jdata['ping']['latency'])) {
                            $ping = $jdata['ping']['latency'];
                        }
                        if (isset($jdata['download']['bandwidth'])) {
                            // Umrechnung von Bit auf MBit mit 2 Nachkommastellen
                            $download = floor(floatval($jdata['download']['bandwidth']) * 8.0 / 10000) / 100;
                        }
                        if (isset($jdata['upload']['bandwidth'])) {
                            // Umrechnung von Bit auf MBit mit 2 Nachkommastellen
                            $upload = floor(floatval($jdata['upload']['bandwidth']) * 8.0 / 10000) / 100;
                        }
                        break;
                }
                $this->SendDebug(__FUNCTION__, ' ... isp=' . $isp . ', ip=' . $ip . ', sponsor=' . $sponsor . ', id=' . $id . ', ping=' . $ping . ', download=' . $download . ', upload=' . $upload, 0);
            }
        }

        if ($ok) {
            $with_logging = $this->ReadPropertyBoolean('with_logging');
            if ($with_logging) {
                $s = 'server=' . $id . '(' . $sponsor . '), duration=' . $duration . ', status=' . ($ok ? 'ok' : 'fail');
                $this->LogMessage($s, KL_MESSAGE);
            }
            $this->SetValue('ISP', $isp);
            $this->SetValue('IP', $ip);
            $this->SetValue('Server', $sponsor);
            $this->SetValue('Ping', $ping);
            $this->SetValue('Upload', $upload);
            $this->SetValue('Download', $download);
            $this->SetStatus(IS_ACTIVE);
        } else {
            $msg = 'failed: exitcode=' . $exitcode . ', err=' . $err;
            $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': ' . $msg, KL_WARNING);
            if (preg_match('/ERROR: No matched servers/', $err)) {
                $this->SetStatus(self::$IS_UNKNOWNSERVER);
            } else {
                $this->SetStatus(self::$IS_SERVICEFAILURE);
            }
            $this->SendDebug(__FUNCTION__, $msg, 0);
        }

        $this->SetValue('LastTest', time());
    }
}
