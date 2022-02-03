<?php

namespace App\Config;

use PDO;

class DB {
    /**
     * Single connection variable
     * @var ?DB $instance
     */
    private static ?DB $instance = null;

    /** @var PDO */
    private PDO $pdo;

    private function __construct() {
        $this->pdo = new PDO(
            getenv("DATABASE_DNS"),
            getenv("DATABASE_USER"),
            getenv("DATABASE_PASSWORD")
        );
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * Method to save data in database
     * @param array $data
     * @param int $isApi
     * @return array array consisting of a code and a message processing the addition
     */
    public function insert(array $data, int $isApi): array {
        if(!$isApi){
            $this->pdo->prepare("UPDATE indexes SET isFree = 1")->execute();
        }
        $returnMessage = [];
        foreach ($data as $value){
            $sql = "SELECT COUNT(`index`) as count FROM indexes WHERE `index` = '{$value['index']}'";

            if (!ctype_digit($value['index'])) {
                $returnMessage['messages'][] = "An error occurred while getting data. The index {$value['index']} must contain only numbers.";
                continue;
            }
            $query = $this->pdo->prepare($sql);
            $query->execute();

            $result = (bool)$query->fetchColumn();

            if($result && !$isApi){
                $this->update($value, $isApi);
                continue;
            }

            $ids = $this->selectIds($value);

            $sql = "INSERT INTO indexes SET 
                `index` = '{$value['index']}', 
                post = '{$value['post']}' , 
                automation = {$value['automation']}, 
                region = {$ids['region']}, 
                utc = {$ids['utc']} , 
                districtOld = {$ids['districtOld']} ,
                districtNew = {$ids['districtNew']} ,
                city = {$ids['city']} , 
                street = '{$value['street']}' , 
                phone = '{$value['phone']}' , 
                isFree = 0, 
                isApi = {$isApi}";

            if(!$this->pdo->prepare($sql)->execute()){
                $returnMessage['messages'][] = "An error occurred while adding index {$value['index']}";
            } else {
                $returnMessage['messages'][] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/$_SERVER[REQUEST_URI]/{$value['index']}";
            }
        }

        if (!$isApi) {
            $this->deleteFree();
        }
        return $returnMessage;
    }

    /**
     * Update database record
     * @param array $value
     * @return void
     */
    public function update(array $value): void {
        $ids = $this->selectIds($value);

        $sql = "UPDATE indexes SET 
                post = '{$value['post']}' , 
                automation = {$value['automation']}, 
                region = {$ids['region']}, 
                utc = {$ids['utc']} , 
                districtOld = {$ids['districtOld']} ,
                districtNew = {$ids['districtNew']} ,
                city = {$ids['city']} , 
                street = '{$value['street']}' , 
                phone = '{$value['phone']}' , 
                isFree = 0, 
                isApi = 0 
                WHERE `index` = '{$value['index']}'";

        $query = $this->pdo->prepare($sql);
        $query->execute();
    }

    /**
     * Select data from database
     *
     * @param array $params contain the current search page
     * @return array array with selected data or error code if an error occurs
     */
    public function select(array $params): array {
        $pageCheck = true;
        $sql = "SELECT i.index, i.post, i.automation, i.street, i.phone,
                    (SELECT name FROM districts d WHERE d.id = i.districtOld) districtOld, 
                    (SELECT name FROM districts d WHERE d.id = i.districtNew) districtNew, 
                    c.name city, r.name region, u.name utc
                FROM indexes i
                    LEFT JOIN regions r ON(i.region = r.id)
                    LEFT JOIN cities c ON(i.city = c.id)
                    LEFT JOIN utc u ON(i.utc = u.id)";
        if (!empty($params['page']) && $params['page'] != 1) {
            if ($pageCheck = ctype_digit($params['page'])) {
                $start = $params['page'] * 50 - 50;
            } else {
                return [
                    'code' => 400,
                    'message' => [
                       'The page must contain only numbers.'
                    ]
                ];
            }
        } else {
            $start = 0;
        }
        if (!empty($params['address'])) {
            $address = $params['address'];
            $sql .= "WHERE u.name LIKE '%$address%' OR c.name LIKE '%$address%' OR r.name LIKE '%$address%' OR districtOld LIKE '%$address%' OR districtNew LIKE '%$address%'";
        }
        $sql .= " ORDER BY i.index LIMIT $start, 50";
        $query = $this->pdo->prepare($sql);
        if ($query->execute()) {
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return [
                'code' => 500,
                'message' => 'Failed to get data'
            ];
        }
    }

    /**
     * @param string $index
     * @return array|bool
     */
    public function selectByIndex(string $index)
    {
        $sql = "SELECT i.index, i.post, i.automation, i.street, i.phone,
                    (SELECT name FROM districts d WHERE d.id = i.districtOld) districtOld, 
                    (SELECT name FROM districts d WHERE d.id = i.districtNew) districtNew, 
                    c.name city, r.name region, u.name utc
                FROM indexes i, regions r, cities c, utc u
                WHERE i.index = '$index'
                LIMIT 1";

        $query = $this->pdo->prepare($sql);
        if ($query->execute() && ctype_digit($index)) {
            return $query->fetch(PDO::FETCH_ASSOC);
        } else {
            return [
                'code' => 400,
                'message' => 'The index must contain only numbers.'
            ];
        }
    }

    /**
     * Removes records from the database at the given indexes
     *
     * @param $indexes
     * @return array an array with code and a message about the result of the deletion
     */
    public function delete($indexes): array {
        $returnMessage = [];
        if (empty($indexes)) {
            return [
                'code' => 400,
                'message' => "The request contains no data."
            ];
        }
        foreach ($indexes as $index) {
            if (!ctype_digit($index)) {
                continue;
            }

            $sql = "SELECT COUNT(`index`) FROM indexes WHERE `index` = '" . $index . "'";
            $query = $this->pdo->prepare($sql);
            $query->execute();
            $exists = $query->fetchColumn();

            if ($exists) {
                $query = $this->pdo->prepare("DELETE FROM indexes WHERE `index` = '".$index."'");
                $query->execute();
            }
        }
        return [
            'code' => 204,
        ];
    }

    /**
     * Selects an ID when inserting and updating indexes
     *
     * @param array $value
     * @return array
     */
    private function selectIds(array $value): array {
        $sql = "SELECT c.id city, r.id region";

        if ($value['districtOld']) {
            $sql .= ", (SELECT d.id FROM districts d WHERE d.name = '{$value['districtOld']}') as districtOld";
        }

        if ($value['districtNew']) {
            $sql .= ", (SELECT d.id FROM districts d WHERE d.name = '{$value['districtNew']}') as districtNew";
        }

        if ($value['utc']){
            $sql .= ", (SELECT u.id FROM utc u WHERE u.name = '{$value['utc']}') as utc";
        }

        $sql .= " FROM cities c, regions r WHERE c.name = '{$value['city']}' AND r.name = '{$value['region']}'";

        $query = $this->pdo->prepare($sql);
        $query->execute();
        $result = $query->fetch();

        //Getting city id
        if ($result['city']) {
            $city = $result['city'];
        } else {
            $sql = "INSERT INTO cities(name) 
                    SELECT '{$value['city']}' FROM DUAL
                    WHERE NOT EXISTS 
                    (SELECT name FROM cities WHERE name='{$value['city']}')";
            $this->pdo->prepare($sql)->execute();
            $city = $this->pdo->lastInsertId();
        }

        //Getting region id
        if ($result['region']) {
            $region = $result['region'];
        } else {
            $sql = "INSERT INTO regions(name) 
                    SELECT '{$value['region']}' FROM DUAL
                    WHERE NOT EXISTS 
                    (SELECT name FROM regions WHERE name='{$value['region']}')";
            $this->pdo->prepare($sql)->execute();
            $region = $this->pdo->lastInsertId();
        }

        //Getting districtOld id
        if (!empty($result['districtOld'])) {
            $districtOld = $result['districtOld'];
        } else if($value['districtOld']) {
            $sql = "INSERT INTO districts(name) 
                    SELECT '{$value['districtOld']}' FROM DUAL
                    WHERE NOT EXISTS 
                    (SELECT name FROM districts WHERE name='{$value['districtOld']}')";
            $this->pdo->prepare($sql)->execute();
            $districtOld = $this->pdo->lastInsertId();
        } else {
            $districtOld = 0;
        }

        //Getting districtNew id
        if (!empty($result['districtNew'])) {
            $districtNew = $result['districtNew'];
        } else if($value['districtNew']) {
            $sql = "INSERT INTO districts(name) 
                    SELECT '{$value['districtNew']}' FROM DUAL
                    WHERE NOT EXISTS 
                    (SELECT name FROM districts WHERE name='{$value['districtNew']}')";
            $this->pdo->prepare($sql)->execute();
            $districtNew = $this->pdo->lastInsertId();
        } else {
            $districtNew = 0;
        }

        //Getting utc id
        if (!empty($result['utc'])) {
            $utc = $result['utc'];
        } else if($value['utc']) {
            $sql = "INSERT INTO utc(name) 
                    SELECT '{$value['utc']}' FROM DUAL
                    WHERE NOT EXISTS 
                    (SELECT name FROM utc WHERE name='{$value['utc']}')";
            $this->pdo->prepare($sql)->execute();
            $utc = $this->pdo->lastInsertId();
        } else {
            $utc = 0;
        }

        return [
            'city' => $city,
            'region' => $region,
            'districtNew' => $districtNew,
            'districtOld' => $districtOld,
            'utc' => $utc,
        ];
    }

    /**
     * Removes indexes from the database if they are not in the file
     *
     * @return void
     */
    private function deleteFree(): void {
        $this->pdo->exec("DELETE FROM indexes WHERE isFree = 1 AND isApi = 0");
    }
}