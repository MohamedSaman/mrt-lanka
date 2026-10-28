<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ProductsImportTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    /**
     * Return sample data for the template
     */
    public function array(): array
    {
        return [
            // Single pricing example
            [
                'CODE' => 'HM0001',
                'NAME' => 'Flasher Musical 12 V',
                'MODEL' => 'FM12V',
                'BRAND' => 'Honda',
                'CATEGORY' => 'Electrical',
                'SUPPLIER' => 'ABC Supplier',
                'SUPPLIER PRICE' => 100.00,
                'RETAIL PRICE' => 150.00,
                'WHOLESALE PRICE' => 140.00,
                'DISTRIBUTOR PRICE' => 130.00,
                'AVAILABLE STOCK' => 50,
                'DAMAGE STOCK' => 0,
                'IMAGE' => 'images/flasher.jpg',
                'DESCRIPTION' => 'Musical flasher for 12V systems',
                'BARCODE' => '1234567890123',
                'STATUS' => 'active',
                'VARIANT NAME' => '',
                'VARIANT VALUES' => '',
            ],
            // Variant-based pricing example
            [
                'CODE' => 'HM0002',
                'NAME' => 'Engine Oil',
                'MODEL' => 'EO-5W30',
                'BRAND' => 'Mobil',
                'CATEGORY' => 'Lubricants',
                'SUPPLIER' => 'Oil Distributor',
                'SUPPLIER PRICE' => '',
                'RETAIL PRICE' => '',
                'WHOLESALE PRICE' => '',
                'DISTRIBUTOR PRICE' => '',
                'AVAILABLE STOCK' => '',
                'DAMAGE STOCK' => '',
                'IMAGE' => 'images/oil.jpg',
                'DESCRIPTION' => 'Premium synthetic engine oil',
                'BARCODE' => '9876543210987',
                'STATUS' => 'active',
                'VARIANT NAME' => 'Size',
                'VARIANT VALUES' => '1L,4L,5L',
            ],
        ];
    }

    /**
     * Define column headings
     */
    public function headings(): array
    {
        return [
            'CODE',
            'NAME',
            'MODEL',
            'BRAND',
            'CATEGORY',
            'SUPPLIER',
            'SUPPLIER PRICE',
            'RETAIL PRICE',
            'WHOLESALE PRICE',
            'DISTRIBUTOR PRICE',
            'AVAILABLE STOCK',
            'DAMAGE STOCK',
            'IMAGE',
            'DESCRIPTION',
            'BARCODE',
            'STATUS',
            'VARIANT NAME',
            'VARIANT VALUES',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Required fields (CODE, NAME) - yellow background
            'A' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
            ],
            'B' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
            ],
        ];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,  // CODE
            'B' => 30,  // NAME
            'C' => 15,  // MODEL
            'D' => 15,  // BRAND
            'E' => 15,  // CATEGORY
            'F' => 20,  // SUPPLIER
            'G' => 18,  // SUPPLIER PRICE
            'H' => 18,  // RETAIL PRICE
            'I' => 18,  // WHOLESALE PRICE
            'J' => 20,  // DISTRIBUTOR PRICE
            'K' => 18,  // AVAILABLE STOCK
            'L' => 18,  // DAMAGE STOCK
            'M' => 25,  // IMAGE
            'N' => 35,  // DESCRIPTION
            'O' => 18,  // BARCODE
            'P' => 12,  // STATUS
            'Q' => 18,  // VARIANT NAME
            'R' => 25,  // VARIANT VALUES
        ];
    }
}