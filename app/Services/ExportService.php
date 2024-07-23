<?php

namespace App\Services;

use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export data to an Excel file and return a streamed response.
     *
     * @param array $data The data to be exported.
     * @param string $fileName The name of the exported file.
     * @return StreamedResponse
     */
    public function export(array $data, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($data) {
            (new FastExcel($data))->export('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
