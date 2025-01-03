<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class Speedtest extends IPSModule
{
    use Speedtest\StubsCommonLib;
    use SpeedtestLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

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

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateData', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModulePrerequisites()
    {
        $r = [];

        $version = $this->ReadPropertyInteger('program_version');
        $path = $this->ReadPropertyString('full_path');
        switch ($version) {
            case self::$MODE_OOKLA:
                $cmd = $path != '' ? $path : 'speedtest';
                $cmd .= ' --version --accept-license --accept-gdpr';
                $prog = 'speedtest';
                break;
            case self::$MODE_SPEEDTEST_CLI:
                $cmd = $path != '' ? $path : 'speedtest-cli';
                $cmd .= ' --version';
                $prog = 'speedtest-cli';
                break;
            default:
                $prog = '';
                return $s;
        }

        if ($prog != '') {
            $data = exec($cmd . ' 2>&1', $output, $exitcode);
            $this->SendDebug(__FUNCTION__, 'cmd=' . $cmd . ', exitcode=' . $exitcode . ', output=' . print_r($output, true), 0);
            if ($exitcode != 0) {
                $r[] = $this->TranslateFormat('Program "{$prog}" is not installed/functional', ['{$prog}' => $prog]);
            }
        } else {
            $r[] = $this->Translate('no valid program version selected');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 0;
        $this->MaintainVariable('ISP', $this->Translate('Internet-Provider'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('IP', $this->Translate('external IP'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('Server', $this->Translate('Server'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('Ping', $this->Translate('Ping'), VARIABLETYPE_FLOAT, 'Speedtest.ms', $vpos++, true);
        $this->MaintainVariable('Upload', $this->Translate('Upload'), VARIABLETYPE_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('Download', $this->Translate('Download'), VARIABLETYPE_FLOAT, 'Speedtest.MBits', $vpos++, true);
        $this->MaintainVariable('LastTest', $this->Translate('Last test'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Internet speedtest');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
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
                    'value'   => self::$MODE_SPEEDTEST_CLI,
                ],
                [
                    'caption' => $this->Translate('Original speedtest from Ookla'),
                    'value'   => self::$MODE_OOKLA,
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
            case self::$MODE_SPEEDTEST_CLI:
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
            case self::$MODE_OOKLA:
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

        if ($version == self::$MODE_SPEEDTEST_CLI) {
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
            'expanded'  => false,
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
            'name'    => 'update_interval',
            'type'    => 'NumberSpinner',
            'suffix'  => 'Minutes',
            'minimum' => 0,
            'caption' => 'Check interval'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Update data',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");',
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'Updating the data takes up to 1 minute'
                ],
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            case 'UpdateData':
                $this->UpdateData();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->MaintainTimer('UpdateData', $msec);
    }

    private function UpdateData()
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
            case self::$MODE_SPEEDTEST_CLI:
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
            case self::$MODE_OOKLA:
                $cmd = $path != '' ? $path : 'speedtest';
                $cmd .= ' --format=json --accept-license --accept-gdpr';

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
            case self::$MODE_SPEEDTEST_CLI:
                if (preg_match('/speedtest-cli: error:\s(.*?)$/', $data, $r)) {
                    $err = $r[1];
                    $ok = false;
                }
                if (preg_match('/^ERROR:\s(.*?)$/', $data, $r)) {
                    $err = $r[1];
                    $ok = false;
                }
                break;
            case self::$MODE_OOKLA:
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
                    case self::$MODE_SPEEDTEST_CLI:
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
                    case self::$MODE_OOKLA:
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
            $this->MaintainStatus(IS_ACTIVE);
        } else {
            $msg = 'failed: exitcode=' . $exitcode . ', err=' . $err;
            $this->LogMessage(__CLASS__ . '::' . __FUNCTION__ . ': ' . $msg, KL_WARNING);
            if (preg_match('/ERROR: No matched servers/', $err)) {
                $this->MaintainStatus(self::$IS_UNKNOWNSERVER);
            } else {
                $this->MaintainStatus(self::$IS_SERVICEFAILURE);
            }
            $this->SendDebug(__FUNCTION__, $msg, 0);
        }

        $this->SetValue('LastTest', time());
    }
}
