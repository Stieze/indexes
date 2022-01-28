<?php

namespace App\Helper\Filter;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter
{
    private int $startRow = 0;
    private int $endRow   = 0;

    /**
     * Set the list of rows that we want to read
     * @param int $startRow
     * @param int $chunkSize
     * @return void
     */
    public function setRows(int $startRow, int $chunkSize): void{
        $this->startRow = $startRow;
        $this->endRow   = $startRow + $chunkSize;
    }

    /**
     * @param int $columnAddress
     * @param int $row
     * @param string $worksheetName
     * @return bool
     */
    public function readCell($columnAddress, $row, $worksheetName = ''): bool {
        if ($row>1 ||($row >= $this->startRow && $row < $this->endRow)) {
            return true;
        }
        return false;
    }
}