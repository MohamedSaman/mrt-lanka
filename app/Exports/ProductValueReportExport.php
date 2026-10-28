<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductValueReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $data;
    protected $total;

    public function __construct($data, $total)
    {
        $this->data = $data;
        $this->total = $total;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Product Code',
            'Product Name',
            'Variant',
            'Available Stock',
            'Supplier Price',
            'Total Value',
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row['product_code'] ?? '',
            $row['product_name'] ?? '',
            $row['variant_value'] ?? 'N/A',
            $row['available_stock'] ?? 0,
            $row['supplier_price'] ?? 0,
            $row['total_value'] ?? 0,
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => ['font' => ['bold' => true]],
        ];
    }
}
