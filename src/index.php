<?php

class AcPassiveUsers
{
    var $pageNumber = 1;
    var $accountName = "";
    var $accountKey = "";
    var $date = "";
    var $listId = "";

    public function __construct($accountName, $accountKey, $date, $listId)
    {
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;
        $this->date = $date;
        $this->listId = $listId;
    }

    public function run()
    {
        do {
            $contacts = $this->getContacts();
            $this->pageNumber++;
        } while (count($contacts) > 3);
    }

    private function getContacts($retries = 1)
    {
        $params = array(
            'api_key' => $this->accountKey,
            'api_action' => 'contact_list',
            'api_output' => 'serialize',
            'filters[listid]' => $this->listId,
            'filters[status]' => '1',
            'full' => 1,
            'page' => $this->pageNumber,
        );
        $url = 'https://' . $this->accountName . '.api-us1.com/admin/api.php?' . $this->serializeParams($params);
        $contacts = $this->makeRequest($url);

        if ($contacts['result_code'] != 1) {
            echo sprintf("Error (getContacts): %s. \n", $contacts['result_message']);

            if ($retries <= 5) {
                return $this->getContacts($retries + 1);
            }
        } else {
            echo sprintf("Contacts count in page %d: %d. \n", $this->pageNumber, count($contacts) - 3);

            foreach ($contacts as $contact) {
                // Проверяю только контакты из ответа api
                if (is_array($contact)) {
                    // По умолчанию контакт пассивен
                    $isPassive = true;

                    // Проверяю actions
                    foreach ($contact['actions'] as $action) {
                        if ($action['type'] === 'open' && strtotime($action['tstamp']) >= strtotime($this->date)) {
                            // Если есть действие "Открытие письма" позже заданной даты
                            // Пользователь НЕ пассивен
                            $isPassive = false;
                            break;
                        }
                    }

                    // Проверяю campaign_history
                    foreach ($contact['campaign_history'] as $campaign) {
                        if (!empty($campaign['reads']) && strtotime($campaign['sdate']) >= strtotime($this->date)) {
                            // Если есть прочтения позже заданной даты
                            // Пользователь НЕ пассивен
                            $isPassive = false;
                            break;
                        }
                    }

                    if ($isPassive && !in_array('passive', $contact['tags'])) {
                        $this->addTag($contact);
                    }
                }
            }
        }

        return $contacts;
    }

    private function addTag($contact, $retries = 1)
    {
        $params = array(
            'api_key' => $this->accountKey,
            'api_action' => 'contact_tag_add',
            'api_output' => 'serialize',
        );
        $url = 'https://' . $this->accountName . '.api-us1.com/admin/api.php?' . $this->serializeParams($params);
        $data = $this->serializeParams(array(
            'id' => $contact["id"],
            'tags' => 'cold',//passive
        ));

        $addTag = $this->makeRequest($url, "POST", $data);
        if ($addTag['result_code'] != 1) {
            echo sprintf("Error (addTag): %s. \n", $addTag['result_message']);

            if ($retries < 3) {
                return $this->addTag($contact["id"], $retries + 1);
            }
        } else {
            echo sprintf("Add tag: \"passive\" for contact: %s %s (%s). \n", $contact["first_name"], $contact["last_name"], $contact["email"]);
        }

        return $addTag;
    }

    private function makeRequest($url, $method = "GET", $data = null)
    {
        $request = curl_init($url);
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($request, CURLOPT_ENCODING, "UTF-8");

        if ($method == "POST") curl_setopt($request, CURLOPT_POST, 1);
        if ($data) curl_setopt($request, CURLOPT_POSTFIELDS, $data);

        $response = (string)curl_exec($request);

        curl_close($request);

        if (!$response) {
            $response = array("result_code" => 0, "result_message" => "Error during making request");
        } else {
            $response = unserialize($response);
        }

        echo sprintf("\nRequest finished with result: (%d) %s. \n", $response["result_code"], $response["result_message"]);
        return $response;
    }

    private function serializeParams($params)
    {
        $query = "";
        foreach ($params as $key => $value) $query .= urlencode($key) . '=' . urlencode($value) . '&';

        return rtrim($query, '& ');
    }
}

if ($argc == 5) {
    echo sprintf("Start script for account: %s. \n\n", $argv[1]);

    $passiveUsers = new AcPassiveUsers($argv[1], $argv[2], $argv[3], $argv[4]);
    $passiveUsers->run();
    echo sprintf("End script for account: %s.", $argv[1]);
} else {
    echo "Not enough arguments. Exit";
    exit(1);
}