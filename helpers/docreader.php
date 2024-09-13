<?php

declare(strict_types=1);


use Smalot\PdfParser\Parser;
use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;

class DOCReader
{
    private const BINARY_EXTENSIONS = ['doc', 'rtf', 'odt', 'docx', 'xlsx', 'ppt', 'pptx', 'pdf'];

    public static function getText(string $file_name, ?string $ext = null): string
    {
        $ext = $ext ?? mb_strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME);
        $type = explode('charset=', $finfo->file($file_name))[1];

        if ($type !== 'binary' && !in_array($ext, self::BINARY_EXTENSIONS, true)) {
            return file_get_contents($file_name) ?: '';
        }

        return match ($ext) {
            'doc' => self::getDOCText($file_name),
            'rtf' => self::getRTFText($file_name),
            'odt' => self::getODTText($file_name),
            'docx' => self::getDOCXText($file_name),
            'xlsx' => self::getXLSXText($file_name),
            'ppt' => self::getPPTText($file_name),
            'pptx' => self::getPPTXText($file_name),
            'pdf' => self::getPDFText($file_name),
            'md', 'markdown', 'html', 'htm' => self::getMarkdownText($file_name),
            default => '',
        };
    }

    public static function getMarkdownText(string $file_name): string
    {
        $content = file_get_contents($file_name) ?: '';
        $converter = new HtmlConverter();
        $content = $converter->convert($content);

        if (str_contains($content, '<table')) {
            $content = preg_replace_callback(
                '/<table(.?)>((\n|.)*?)<\/table>/mi',
                static function ($matches) {
                    $converter = new HtmlConverter();
                    $converter->getEnvironment()->addConverter(new TableConverter());
                    return $converter->convert($matches[0]);
                },
                $content
            );
        }

        return $content;
    }

    public static function getODTText(string $file_name): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file_name) === true) {
            if (($index = $zip->locateName('content.xml')) !== false) {
                $xml = new DOMDocument();
                $xml->loadXML($zip->getFromIndex($index), LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $zip->close();
                return $xml->saveXML() ?: '';
            }
            $zip->close();
        }
        return '';
    }

    public static function getRTFText(string $file_name): string
    {
        $text = file_get_contents($file_name) ?: '';
        if (empty($text)) {
            return '';
        }

        $text = preg_replace([
            '/(\\\\f\w+|\\\\b\w+|\\\\\w+|\\\\\'\w+)/m',
            '/\\\\\n/m',
            '/\n\s+/m',
            '/-\d+\n/m',
            '/{\n?.*\n.*?}/m',
            '/{{\\\\\*{HYPERLINK .*}}/m'
        ], '', $text);

        return str_replace("\n", ' ', trim($text));
    }

    public static function getDOCText(string $file_name): string
    {
        if (($fh = fopen($file_name, 'rb')) === false) {
            return '';
        }

        $headers = fread($fh, 0xa00) ?: '';
        $n1 = (ord($headers[0x21c] ?? "\x00") - 1);
        $n2 = ((ord($headers[0x21d] ?? "\x00") - 8) * 256);
        $n3 = (ord($headers[0x21e] ?? "\x00") * 256 * 256);
        $n4 = (ord($headers[0x21f] ?? "\x00") * 256 * 256 * 256);

        $textLength = $n1 + $n2 + $n3 + $n4;
        $extracted_plaintext = fread($fh, $textLength) ?: '';
        fclose($fh);

        return nl2br(mb_convert_encoding($extracted_plaintext, 'UTF-8'));
    }

    public static function getDOCXText(string $file_name): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file_name) === true) {
            if (($xml_index = $zip->locateName('word/document.xml')) !== false) {
                $xml_data = $zip->getFromIndex($xml_index);
                $zip->close();

                $xml_data = preg_replace([
                    '/<w:p w[0-9-Za-z]+:[a-zA-Z0-9]+="[a-zA-z"0-9 :="]+">/',
                    "/<w:tr>/",
                    "/<w:tab\/>/"
                ], ["\n\r", "\n\r", "\t"], $xml_data);

                return strip_tags(str_replace("</w:p>", "\n\r", $xml_data));
            }
            $zip->close();
        }
        return '';
    }

    public static function getPDFText(string $file_name): string
    {
        $parser = new Parser();
        return $parser->parseFile($file_name)->getText();
    }

    public static function getXLSXText(string $file_name): string
    {
        $content = '';
        $dir = sys_get_temp_dir() . '/' . uniqid('xlsx_', true);
        mkdir($dir);

        $zip = new ZipArchive();
        if ($zip->open($file_name) === true) {
            $zip->extractTo($dir);
            $zip->close();

            $strings = simplexml_load_file($dir . '/xl/sharedStrings.xml');
            $sheet = simplexml_load_file($dir . '/xl/worksheets/sheet1.xml');

            foreach ($sheet->sheetData->row as $row) {
                foreach ($row->c as $cell) {
                    if (isset($cell['t']) && $cell['t'] == 's') {
                        $cellIndex = (int)$cell->v;
                        $si = $strings->si[$cellIndex];
                        $si->registerXPathNamespace('n', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                        foreach ($si->xpath(".//n:t") as $t) {
                            $content .= (string)$t . '  ';
                        }
                    }
                }
            }

            self::rrmdir($dir);
        }

        return $content;
    }

    public static function getPPTText(string $file_name): string
    {
        $content = file_get_contents($file_name) ?: '';
        $lines = explode(chr(0x0f), $content);
        $outtext = "";

        foreach ($lines as $line) {
            if (strpos($line, chr(0x00) . chr(0x00) . chr(0x00)) === 1) {
                $text_line = substr($line, 4);
                $end_pos = strpos($text_line, chr(0x00));
                if ($end_pos !== false) {
                    $text_line = substr($text_line, 0, $end_pos);
                    $text_line = preg_replace('/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/', '', $text_line);
                    $outtext = $text_line . "\n" . $outtext;
                }
            }
        }

        return $outtext;
    }

    public static function getPPTXText(string $file_name): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file_name) === true) {
            $slideNumber = 1;
            $output_text = '';

            while (($xml_index = $zip->locateName("ppt/slides/slide{$slideNumber}.xml")) !== false) {
                $xml_data = $zip->getFromIndex($xml_index);
                $xml_handle = new DOMDocument();
                $xml_handle->loadXML($xml_data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $output_text .= $xml_handle->saveXML();
                $slideNumber++;
            }

            $zip->close();
            return $output_text;
        }

        return '';
    }

    private static function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    is_dir($path) ? self::rrmdir($path) : unlink($path);
                }
            }
            rmdir($dir);
        }
    }
}
