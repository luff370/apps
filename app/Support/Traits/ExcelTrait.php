<?php

namespace App\Support\Traits;

use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Common\Type;
use Box\Spout\Common\Entity\Style\CellAlignment;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Writer\Common\Creator\WriterFactory;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ExcelTrait
{
    /**
     * 读取excel文件数据
     *
     * @param $path
     *
     * @return array
     * @throws IOException
     * @throws UnsupportedTypeException
     * @throws ReaderNotOpenedException
     */
    public function readerFromExcel($path): array
    {
        $reader = ReaderFactory::createFromType(Type::XLSX);
        $reader->setShouldFormatDates(true);
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $num => $sheet) {
            // 只读取第一个sheet的内容
            if ($num > 1) {
                break;
            }

            foreach ($sheet->getRowIterator() as $i => $row) {
                $rows[] = $row->toArray();
            }
        }

        return $rows;
    }

    /**
     * 导出excel
     *
     * @param string $fileName
     * @param array $data
     * @param array $header
     * @param bool $download
     *
     * @return Application|UrlGenerator|\Illuminate\Foundation\Application|string|BinaryFileResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportToExcel(string $fileName, array $data, array $header = [], $download = false)
    {
        ini_set('memory_limit', -1);

        $file = storage_path('app/public/exports/') . $fileName . '.xlsx';
        $downloadUrl = url(\Storage::url('exports/' . $fileName . '.xlsx'));

        if ($header) {
            $data = array_merge([$header], $data);
        }

        $writer = WriterFactory::createFromType(Type::XLSX);
        $writer->openToFile($file);

        $style = (new StyleBuilder)
            ->setFontBold()
            ->setShouldWrapText()
            ->setCellAlignment(CellAlignment::CENTER)
            ->build();

        foreach ($data as $item) {
            $row = WriterEntityFactory::createRowFromArray($item, $style);
            $writer->addRow($row);
        }

        $writer->close();

        if ($download) {
            return response()
                ->download($file)
                ->deleteFileAfterSend(true);
        }

        return $downloadUrl;
    }

    /**
     * @param string $fileName 文件名称
     * @param array $data 数据
     * @param array $headerData 首行数据
     * @param false $download 是否直接下载
     *
     */
    public function exportToCsv($fileName, $data, $headerData = [], $download = false)
    {
        $file = storage_path('app/public/exports/') . $fileName . '.csv';
        $downloadUrl = config('app.url') . \Storage::url('exports/' . $fileName . '.csv');

        $fp = fopen($file, 'w');

        fwrite($fp, "\xEF\xBB\xBF");

        if (!empty($headerData)) {
            fputcsv($fp, $headerData);
        }

        $num = 0;
        //每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
        $limit = 100000;
        //逐行取出数据，不浪费内存
        $count = count($data);
        if ($count > 0) {
            foreach ($data as $i => $row) {
                $num++;
                //刷新一下输出buffer，防止由于数据过多造成问题
                if ($limit == $num) {
                    ob_flush();
                    flush();
                    $num = 0;
                }
                fputcsv($fp, $row);
            }
        }
        fclose($fp);

        if ($download) {
            return response()
                ->download($file)
                ->deleteFileAfterSend(true);
        }

        return $downloadUrl;
    }
}
