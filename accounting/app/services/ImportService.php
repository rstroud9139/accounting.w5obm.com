<?php
class ImportService
{
    public function guessType($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, array('csv'))) return 'csv';
        if (in_array($ext, array('qif'))) return 'qif';
        if (in_array($ext, array('ofx', 'qbo', 'qfx'))) return 'ofx';
        if (in_array($ext, array('iif'))) return 'iif';
        if (in_array($ext, array('gnucash', 'gcz'))) return 'gnucash';
        return 'csv';
    }

    public function parseFile($type, $tmpPath)
    {
        $type = strtolower($type);
        if ($type === 'csv') return $this->parseCSV($tmpPath);
        if ($type === 'qif') return $this->parseQIF($tmpPath);
        if ($type === 'ofx') return $this->parseOFX($tmpPath);
        if ($type === 'iif') return $this->parseIIF($tmpPath);
        if ($type === 'gnucash') return $this->parseGnuCash($tmpPath);
        return false;
    }

    private function parseCSV($tmpPath)
    {
        $fh = fopen($tmpPath, 'r');
        if (!$fh) return false;
        $header = fgetcsv($fh);
        if ($header === false) return false;
        $cols = array_map('strtolower', $header);
        $rows = array();
        while (($row = fgetcsv($fh)) !== false) {
            $rec = array_combine($cols, $row);
            if (!$rec) continue;
            $dateRaw = isset($rec['date']) ? $rec['date'] : '';
            $date = $this->normalizeDate($dateRaw);
            $amountRaw = isset($rec['amount']) ? $rec['amount'] : (isset($rec['amt']) ? $rec['amt'] : '0');
            $amount = $this->toFloat($amountRaw);
            $type = isset($rec['type']) ? $rec['type'] : null; // 'Income'/'Expense' recommended
            $rows[] = array(
                'date' => $date ? $date : date('Y-m-d'),
                'amount' => $amount,
                'type' => $type,
                'description' => isset($rec['description']) ? $rec['description'] : (isset($rec['memo']) ? $rec['memo'] : ''),
                'payee' => isset($rec['payee']) ? $rec['payee'] : '',
                'memo' => isset($rec['memo']) ? $rec['memo'] : '',
                'category' => isset($rec['category']) ? $rec['category'] : '',
                'account' => isset($rec['account']) ? $rec['account'] : '',
                'account_type' => isset($rec['account_type']) ? $rec['account_type'] : '',
            );
        }
        fclose($fh);
        return $this->packageResult($rows);
    }

    private function parseQIF($tmpPath)
    {
        $lines = file($tmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return false;
        $rows = array();
        $cur = array();
        foreach ($lines as $line) {
            $p = substr($line, 0, 1);
            $v = substr($line, 1);
            switch ($p) {
                case '!': // header type; ignore for now
                    break;
                case 'D': // date
                    $cur['date'] = $this->normalizeDate($v);
                    break;
                case 'T': // amount
                    $cur['amount'] = $this->toFloat($v);
                    break;
                case 'P': // payee
                    $cur['payee'] = $v;
                    break;
                case 'M': // memo
                    $cur['memo'] = $v;
                    break;
                case 'L': // category
                    $cur['category'] = $v;
                    break;
                case '^': // end of record
                    if (!empty($cur)) {
                        $rows[] = array(
                            'date' => isset($cur['date']) ? $cur['date'] : date('Y-m-d'),
                            'amount' => isset($cur['amount']) ? $cur['amount'] : 0,
                            'type' => null, // infer later
                            'description' => isset($cur['memo']) ? $cur['memo'] : (isset($cur['payee']) ? $cur['payee'] : ''),
                            'payee' => isset($cur['payee']) ? $cur['payee'] : '',
                            'memo' => isset($cur['memo']) ? $cur['memo'] : '',
                            'category' => isset($cur['category']) ? $cur['category'] : ''
                        );
                    }
                    $cur = array();
                    break;
            }
        }
        return $this->packageResult($rows);
    }

    private function parseOFX($tmpPath)
    {
        $txt = file_get_contents($tmpPath);
        if ($txt === false) return false;
        $rows = array();
        // Handle simple OFX SGML or XML by extracting STMTTRN blocks
        $blocks = preg_split('/<\s*STMTTRN\s*>/i', $txt);
        foreach ($blocks as $b) {
            if (stripos($b, '</STMTTRN>') === false && stripos($b, '<STMTTRN>') === false) {
                // first chunk before first block
                continue;
            }
            $date = $this->ofxTag($b, 'DTPOSTED');
            $amt = $this->ofxTag($b, 'TRNAMT');
            $name = $this->ofxTag($b, 'NAME');
            $memo = $this->ofxTag($b, 'MEMO');
            $trntype = $this->ofxTag($b, 'TRNTYPE');
            $rows[] = array(
                'date' => $this->normalizeDate($date),
                'amount' => $this->toFloat($amt),
                'type' => $this->mapOfxType($trntype, $amt),
                'description' => $name ? $name : $memo,
                'payee' => $name,
                'memo' => $memo,
                'category' => ''
            );
        }
        return $this->packageResult($rows);
    }

    private function parseIIF($tmpPath)
    {
        $lines = file($tmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return false;
        $rows = array();
        foreach ($lines as $line) {
            if ($line[0] === '!') continue; // header directives
            $parts = explode("\t", $line);
            // Heuristic: look for TRNS rows: TRNS\t<TRNSTYPE>\t<DATE>\t<AMOUNT>\t<NAME>\t<MEMO>
            if (isset($parts[0]) && strtoupper($parts[0]) === 'TRNS') {
                $date = $this->normalizeDate(isset($parts[2]) ? $parts[2] : '');
                $amount = $this->toFloat(isset($parts[3]) ? $parts[3] : '0');
                $name = isset($parts[4]) ? $parts[4] : '';
                $memo = isset($parts[5]) ? $parts[5] : '';
                $trnstype = strtoupper(isset($parts[1]) ? $parts[1] : '');
                $rows[] = array(
                    'date' => $date,
                    'amount' => $amount,
                    'type' => ($trnstype === 'DEPOSIT') ? 'Income' : (($trnstype === 'CHECK' || $amount < 0) ? 'Expense' : null),
                    'description' => $memo ? $memo : $name,
                    'payee' => $name,
                    'memo' => $memo,
                    'category' => ''
                );
            }
        }
        return $this->packageResult($rows);
    }

    private function parseGnuCash($tmpPath)
    {
        $xml = $this->readGnuCashXml($tmpPath);
        if ($xml === false) return false;

        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE)) {
            return false;
        }

        $xpath = new DOMXPath($dom);
        $namespaces = array(
            'gnc' => 'http://www.gnucash.org/XML/gnc',
            'act' => 'http://www.gnucash.org/XML/act',
            'trn' => 'http://www.gnucash.org/XML/trn',
            'spl' => 'http://www.gnucash.org/XML/split',
            'cmdty' => 'http://www.gnucash.org/XML/cmdty',
            'ts' => 'http://www.gnucash.org/XML/ts',
        );
        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $accounts = array();
        $accountNodes = $xpath->query('//gnc:account');
        foreach ($accountNodes ?: array() as $node) {
            $guid = trim($xpath->evaluate('string(act:id)', $node));
            if ($guid === '') continue;
            $accounts[$guid] = array(
                'guid' => $guid,
                'name' => trim($xpath->evaluate('string(act:name)', $node)),
                'code' => trim($xpath->evaluate('string(act:code)', $node)),
                'type' => trim($xpath->evaluate('string(act:type)', $node)),
            );
        }

        $rows = array();
        $transactions = $xpath->query('//gnc:transaction');
        foreach ($transactions ?: array() as $txnNode) {
            $txnDescription = trim($xpath->evaluate('string(trn:description)', $txnNode));
            $txnNumber = trim($xpath->evaluate('string(trn:number)', $txnNode));
            $posted = trim($xpath->evaluate('string(trn:date-posted/ts:date)', $txnNode));

            $splitNodes = $xpath->query('trn:splits/trn:split', $txnNode);
            foreach ($splitNodes ?: array() as $splitNode) {
                $accountGuid = trim($xpath->evaluate('string(spl:account)', $splitNode));
                $memo = trim($xpath->evaluate('string(spl:memo)', $splitNode));
                $valueRaw = trim($xpath->evaluate('string(spl:value)', $splitNode));
                $amount = $this->gnucashFractionToFloat($valueRaw);

                $rows[] = array(
                    'date' => $this->normalizeDate($posted ?: date('Y-m-d')),
                    'amount' => round($amount, 2),
                    'type' => ($amount >= 0) ? 'Income' : 'Expense',
                    'description' => $txnDescription ?: $memo,
                    'payee' => $accounts[$accountGuid]['name'] ?? '',
                    'memo' => $memo,
                    'category' => $accounts[$accountGuid]['name'] ?? '',
                    'account' => $accounts[$accountGuid]['name'] ?? '',
                    'account_type' => $accounts[$accountGuid]['type'] ?? '',
                    'account_guid' => $accountGuid,
                    'reference' => $txnNumber,
                );
            }
        }

        return $this->packageResult($rows, array(
            'accounts' => array_values($accounts),
            'categories' => $this->inferCategorySeeds($rows, $accounts),
            'meta' => array('source' => 'GnuCash XML')
        ));
    }

    private function readGnuCashXml($path)
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }
        if (strncmp($contents, "\x1F\x8B", 2) === 0) {
            $decoded = @gzdecode($contents);
            if ($decoded === false) {
                return false;
            }
            return $decoded;
        }
        return $contents;
    }

    private function gnucashFractionToFloat($value)
    {
        $value = trim((string)$value);
        if ($value === '') return 0.0;
        if (strpos($value, '/') !== false) {
            list($num, $den) = explode('/', $value, 2);
            $den = (float)$den ?: 1.0;
            return (float)$num / $den;
        }
        return (float)$value;
    }

    private function ofxTag($block, $tag)
    {
        // Support <TAG>value or <TAG>value</TAG>
        if (preg_match('/<' . preg_quote($tag, '/') . '>([^<\r\n]+)/i', $block, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function mapOfxType($trntype, $amount)
    {
        $t = strtoupper(trim((string)$trntype));
        if ($t === 'CREDIT') return 'Income';
        if ($t === 'DEBIT') return 'Expense';
        if ($t === 'DEP') return 'Income';
        if ($t === 'CHECK' || $t === 'PAYMENT') return 'Expense';
        return ($this->toFloat($amount) >= 0) ? 'Income' : 'Expense';
    }

    private function normalizeDate($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') return date('Y-m-d');
        // OFX: YYYYMMDD or YYYYMMDDHHMMSS
        if (preg_match('/^\d{8}(\d{6})?/', $raw)) {
            $y = substr($raw, 0, 4);
            $m = substr($raw, 4, 2);
            $d = substr($raw, 6, 2);
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        // Common: MM/DD/YYYY or DD/MM/YYYY – assume US by default
        if (strpos($raw, '/') !== false) {
            $ts = strtotime($raw);
            if ($ts !== false) return date('Y-m-d', $ts);
        }
        // ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function toFloat($v)
    {
        $v = trim((string)$v);
        $v = str_replace(array(',', '$'), '', $v);
        return (float)$v;
    }

    private function packageResult(array $transactions, array $extras = array()): array
    {
        $transactions = array_values($transactions);
        $payload = array(
            'transactions' => $transactions,
            'categories' => $extras['categories'] ?? $this->inferCategorySeeds($transactions),
            'accounts' => $extras['accounts'] ?? $this->inferAccountSeeds($transactions),
            'meta' => $extras['meta'] ?? array(),
        );
        return $payload;
    }

    private function inferCategorySeeds(array $transactions, array $accountLookup = array()): array
    {
        $seeds = array();
        foreach ($transactions as $row) {
            $name = '';
            if (!empty($row['category'])) {
                $name = trim((string)$row['category']);
            }
            if ($name === '' && !empty($row['payee']) && !empty($row['type'])) {
                $name = trim((string)$row['payee']);
            }
            if ($name === '') {
                continue;
            }
            $type = isset($row['type']) ? $row['type'] : ((isset($row['amount']) && (float)$row['amount'] >= 0) ? 'Income' : 'Expense');
            $key = strtolower($name . '|' . $type);
            if (!isset($seeds[$key])) {
                $seeds[$key] = array('name' => $name, 'type' => $type);
            }
        }

        if (empty($seeds) && !empty($accountLookup)) {
            foreach ($accountLookup as $acc) {
                $accName = trim((string)($acc['name'] ?? ''));
                if ($accName === '') {
                    continue;
                }
                $accType = strtoupper((string)($acc['type'] ?? ''));
                if (!in_array($accType, array('INCOME', 'EXPENSE'), true)) {
                    continue;
                }
                $key = strtolower($accName . '|' . $accType);
                if (!isset($seeds[$key])) {
                    $seeds[$key] = array(
                        'name' => $accName,
                        'type' => $accType === 'INCOME' ? 'Income' : 'Expense',
                    );
                }
            }
        }

        return array_values($seeds);
    }

    private function inferAccountSeeds(array $transactions): array
    {
        $seeds = array();
        foreach ($transactions as $row) {
            $name = trim((string)($row['account_name'] ?? $row['account'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = isset($row['account_type']) ? (string)$row['account_type'] : null;
            $key = strtolower($name . '|' . $type);
            if (!isset($seeds[$key])) {
                $seeds[$key] = array(
                    'name' => $name,
                    'type' => $type,
                );
            }
        }
        return array_values($seeds);
    }
}
