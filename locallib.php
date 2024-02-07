<?php

namespace Grade\Report;

class SpTable
{
    final public static function getRangeByColumnAndRow(int $col_first, int $row_first, int $col_last, int $row_last, string $sheet_name='')
    {
        $first = self::getCellNameByColumnAndRow($col_first, $row_first);
        $last  = self::getCellNameByColumnAndRow($col_last, $row_last);

        if ($sheet_name === '') {
            return $first.':'.$last;
        }

        return $sheet_name.'!'.$first.':'.$last;
    }

    final static public function getCellNameByColumnAndRow(int $col, int $row)
    {
        // Convert int to string: 1->A ... 26->Z, 27->AA ...
        $col_str = '';

        $m = 1;
        while ($col > 0) {
            $chr_code = $col % 26;
            if ($chr_code === 0) {
                $chr_code = 26;
            }
            $col = ($col - $chr_code) / 26;
           
            $col_str = chr(64 + $chr_code) . $col_str;
            $m++;
        };
        return $col_str.$row;
    }
}