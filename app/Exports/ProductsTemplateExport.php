<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /**
     * Return sample data for the template
     */
    public function array(): array
    {
        return [
            ['USN0001', 'Flasher Musical 12 V', 100.00, 150.00, 140.00, 50],
            ['USN0002', 'Flasher Musical 24 V', 120.00, 180.00, 170.00, 30],
            ['USN0003', 'Flasher Electrical 12 V', 90.00, 140.00, 130.00, 40],
            ['USN0004', 'Flasher Electrical 24 V', 110.00, 160.00, 150.00, 25],
        ];
    }

    /**
     * Return column headings
     */
    public function headings(): array
    {
        return [
            'CODE',
            'NAME',
            'SUPPLIER PRICE',
            'RETAIL PRICE',
            'WHOLESALE PRICE',
            'AVAILABLE STOCK',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row (headers)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4CAF50']
                ],
            ],
        ];
    }

    /**
     * Return the worksheet title
     */
    public function title(): string
    {
        return 'Products Import Template';
    }
}
