<?php

namespace App\Command;


use App\Config\DB;
use App\Helper\Filter\ChunkReadFilter;
use App\Helper\ApiZIP;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\{Command\Command, Input\InputInterface, Output\OutputInterface};
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class UpdateVPZ extends Command
{
    private DB $db;
    private ApiZIP $zip;
    private array $keys = [
        'index',
        'post',
        'automation',
        'region',
        'districtOld',
        'districtNew',
        'utc',
        'city',
        'street',
        'phone',
    ];

    public function __construct(string $name = null) {
        parent::__construct($name);
        $this->db = DB::getInstance();
        $this->zip = new ApiZip();
    }

    protected function configure(): void {
        $this
            ->setName('app:update-vpz-indexes')
            ->setDescription('Download, unzip and save ukrposhta indexes in database')
            ->setHelp('This command allows you refresh Algolia dataset with latest post.');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->zip->download("https://ukrposhta.ua/postindex/upload/postvpz.zip");
        $this->zip->unZip();
        $path = getenv("TEMP_FOLDER") . getenv("FILE_NAME") . ".xls";

        $inputFileType = IOFactory::identify($path);

        $reader = IOFactory::createReader($inputFileType);

        $chunkFilter = new ChunkReadFilter();

        $chunkSize = 10000;

        $reader->setReadFilter($chunkFilter);

        $countRows = $reader->load($path)->getActiveSheet()->getHighestRow();

        if ($countRows < 10000) {
            $chunkSize = 9999;
        }

        for ($startRow = 2; $startRow <= $countRows; $startRow += $chunkSize) {
            $data = [];

            $chunkFilter->setRows($startRow,$chunkSize);

            $spreadsheet = $reader->load($path);

            $worksheet = $spreadsheet->getActiveSheet()->toArray();

            foreach ($worksheet as $row){
                $key = reset($this->keys);

                if (empty($row[0]) || !is_numeric($row[0])) {
                    continue;
                }

                $index = $row[0];

                foreach ($row as $cell) {
                    $data[$index][$key] = addslashes($cell);
                    $key = next($this->keys);
                }
            }
            $this->db->insert($data, 0);
        }

        unlink(getenv("TEMP_FOLDER") . getenv("FILE_NAME") . ".xls");

        return 1;
    }
}

