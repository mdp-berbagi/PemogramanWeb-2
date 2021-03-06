<?php
require_once(__DIR__ . "/../config/database.php");

/**
 * Obyek utama untuk model
 * 
 * @property string $hostname
 * @property string $username
 * @property string $password
 * @property string $database
 * @property string $table
 * 
 * @property array $lastError
 * @property array $joinQuery
 * 
 * @property PDO $DB
 * 
 * @method __construct
 * @method addOnce
 * @method editRow
 * @method getRow
 * @method getAll
 * @method deleteRow
 * 
 * 
 */
class Model {
    // Gunakan konfigurasi Database
    use databaseConfiguration;

    // PDO Objek Place
    protected PDO $DB;

    // simpan error terakhir
    public Array $lastError = [];

    private array $joinQuery = [];

    /**
     * Konstruktor Memuat Objek PDO
     * 
     * @return PDO
     * 
     */
    public final function __construct()
    {
        return $this->connect();
    }

    /**
     * Koneksi ke database via PDO
     * 
     * @return Model
     */
    private function connect() {
        try {
            $this->DB = new PDO(
                "mysql:host={$this->hostname};dbname={$this->database}", 
                $this->username, 
                $this->password
            );

            $this->DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $this->DB;
        }catch(PDOException $e) {
            echo "Koneksi Gagal : " . $e->getMessage();
            exit;
        }
    }

    /**
     * Tambah data baru pada table
     * 
     * @param array $newData
     * 
     * @return bool
     * @return int
     */
    function addOnce(Array $newData) : bool {
        $database = $this->DB;

        // ambil field dari array keys
        $field  = "`".implode("`,`", array_keys($newData))."`";


        // looping untuk mendapatkan berapa banyak tanda tanya
        for($i = 0; $i < count($newData); $i++){
            $question_symbol[] = "?";
        }


        // hasil array dari looping di gabung ($question_symbol nilainya ['?','?'] menjadi "?,?")
        $question_symbol = implode(",", $question_symbol);


        // pripare query dan masukan dataset tadi ke sana
        $result = $database->prepare("INSERT INTO `{$this->table}`({$field}) VALUES ({$question_symbol})");

        /**
         * lakukan query pripare dengan kasih parm dataSetnya denga array values
         * 
         * array values mengembalikan nilai [0 => 1822250092, 1 => 'abdul aziz']
         * execute harus menggunakan array values
         */
        if(!$result->execute(array_values($newData))) {
            $this->lastError = $result->errorInfo();
        }

        /**
         * mengembalikan nilai balik dengan ID mahasiswa yg baru di masukan
         * 
         * (int) itu mengubah numeric menjadi number
         * numeric itu text tapi nilainy angka misal di java String x = "1" di ubah jadi Int x = 1 dengan (int) itu
         */
        return $database->LastInsertId() < 1 ? false : (int) $database->LastInsertId();
    }

     /**
     * Edit data dari table berdasarkan ID
     * 
     * @param int $id
     * @param array $dataSet
     * 
     * @return bool 
     */
    function editRow(Int $id, Array $dataSet = []) : bool {
        $database = $this->DB;


        // jadikan datasetna menjadi array, misal : ["'npm' => '?'", "'nama' => '?'"]
        foreach($dataSet as $field => $value) {
            $setQuery[] = "`{$field}` = ?";
        }


        // gabungin array tadi dari ["'npm' => '?'", "'nama' => '?'"] menjadi string "'npm' => '?', 'nama' => '?'" 
        $setQuery = implode(",", $setQuery);


        // pripare query dan masukan dataset tadi ke sana
        $result = $database->prepare("UPDATE `{$this->table}` SET {$setQuery} WHERE id = {$id}");


        /**
         * lakukan query pripare dengan kasih parm dataSetnya denga array values
         * 
         * array values mengembalikan nilai [0 => 1822250092, 1 => 'abdul aziz']
         * execute harus menggunakan array values
         */
        if(!$result->execute(array_values($dataSet))) {
            $this->lastError = $result->errorInfo();
        };

        /**
         * Balikin hasilnya true atau false (prosesnya berhasil / enggak)
         * 
         * rowCount akan mengebalikan nilai total data yang terubah ( kasus sekarang pasti satu )
         * dibuat ada > 0 supaya nilai baliknya true atau false
         */
        return ($result->rowCount() > 0);
    }

    /**
     * Ambil satu baris pada table berdasarkan ID
     * 
     * @param int $id
     * 
     * @return array
     */
    function getRow(Int $id) : array {
        $database = $this->DB;

        $queryToJoin = "";
        $querySelection[] = "`{$this->table}`.*";

        foreach($this->joinQuery as $field) {
            $queryToJoin .= " ".$field['query'];
            $querySelection[] = $field['selection'];
        }

        $querySelection = implode(", ", $querySelection);

        // pripare biasa sama execute biasa
        $query = $database->prepare("SELECT {$querySelection} FROM `{$this->table}` {$queryToJoin} WHERE `{$this->table}`.`id`= '{$id}' LIMIT 1");
        $query->execute();

        // kembalikan nilai dengan PDO::FETCH_ASSOC untuk buat arrayny satu saja
        return $query->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Hapus Satu baris pada tabel berdasarkan ID
     * 
     * @param int $id
     * @return bool
     */
    function deleteRow($id) : bool {
        // ekseksi dan mengambil brp baris yang berubah
        $deleted = $this->DB->exec("DELETE FROM `{$this->table}` WHERE id = '{$id}'");

        return ($deleted > 0);
    }

    /**
     * Ambil semua data pada tabel
     * 
     * @return array
     */
    function getAll() : array {
        $queryToJoin = "";
        $querySelection[] = "`{$this->table}`.*";

        foreach($this->joinQuery as $field) {
            $queryToJoin .= " ".$field['query'];
            $querySelection[] = $field['selection'];
        }

        $querySelection = implode(", ", $querySelection);

        // mengambil semuanya
        return $this->DB->query("SELECT {$querySelection} FROM `{$this->table}` {$queryToJoin}")->fetchAll();
    }

    /**
     * Menghubungkan tabel dengan tabel lain
     * ini harus di setting terlebih dahulu di model agar bisa digunakan
     * 
     * @param string $table_name
     * @param array $selection
     * 
     * @return Model
     */
    function joinWith(String $table_name, Array $selection) : Model {

        foreach($selection as $target => $asName) {
            $querySelection[] = "`{$table_name}`.`{$target}` as `{$asName}`";
        }

        $this->joinQuery[$table_name] = [
            'query' => "join `{$table_name}` on {$this->join[$table_name]}",
            'selection' => implode(", ", $querySelection)
        ];

        return $this;
    }
}