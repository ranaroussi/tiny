<?php
tiny::helpers(['geos']);

// setasign/fpdf
class InvoiceGenerator extends FPDF
{
    public const INVOICE_SIZE_LEGAL = 'legal';
    public const INVOICE_SIZE_LETTER = 'letter';
    public const INVOICE_SIZE_A4 = 'a4';

    public const NUMBER_SEPARATOR_DOT = '.';
    public const NUMBER_SEPARATOR_COMMA = ',';
    public const NUMBER_SEPARATOR_SPACE = ' ';

    public const NUMBER_ALIGNMENT_LEFT = 'left';
    public const NUMBER_ALIGNMENT_RIGHT = 'right';

    public const ICONV_CHARSET_OUTPUT = 'ISO-8859-1//TRANSLIT';

    public $lang = [
        'number'   => 'Invoice number',
        'date'     => 'Date of issue',
        'time'     => 'Issue time',
        'due'      => 'Date due',
        'to'       => 'Bill to',
        'from'     => 'Billing from',
        'description'  => 'Description',
        'qty'      => 'Qty',
        'price'    => 'Unit price',
        'discount' => 'Discount',
        'vat'      => 'VAT',
        'total'    => 'Amount',
        'page'     => 'Page',
        'page_of'  => 'of',
    ];

    public $angle = 0;
    public $font = 'arial';                 /* Font Name : See inc/fpdf/font for all supported fonts */
    public $columnOpacity = 0;                  /* Items table background color opacity. Range (0.00 - 1) */
    public $columnSpacing = 0;                /* Spacing between Item Tables */
    public $referenceformat = [                 /* Currency formater */
        'decimals_sep' => self::NUMBER_SEPARATOR_DOT,       /* Separator before decimals */
        'thousands_sep' => self::NUMBER_SEPARATOR_COMMA,    /* Separator between group of 3 numbers */
        'alignment' => self::NUMBER_ALIGNMENT_LEFT,         /* Price alignment in the column */
        'space' => false,                                   /* Space between currency and amount */
        'negativeParenthesis' => false                      /* Parenthesis arund price */
    ];
    public $margins = [
        'l' => 10,
        't' => 10,
        'r' => 10,
    ]; /* l: Left Side , t: Top Side , r: Right Side */
    public $fontSizeProductDescription = 7.5;                /* font size of product description */

    public $document;
    public $type;
    public $reference;
    public $logo;
    public $color;
    public $badgeColor;
    public $date;
    public $time;
    public $due;
    public $from;
    public $to;
    public $items;
    public $totals;
    public $badge;
    public $addText;
    public $footernote;
    public $dimensions;
    public $customHeaders = [];
    public $currency;
    public $currencyCode;
    public $maxImageDimensions;
    public $firstColumnWidth;
    public $title;
    public $productsEnded;
    public $invoiceTotal;
    protected $columns = 1;

    public function __construct(
        string $currencyCode = 'USD',
        string $size = self::INVOICE_SIZE_LETTER
    ) {
        $this->items = $this->totals = $this->addText = [];
        $this->currency = $this->convert(tiny::geos()->currencySymbol($currencyCode));
        $this->currencyCode = $this->convert(strtoupper($currencyCode));
        $this->maxImageDimensions = $this->dimensions = [56, 56];
        $this->from = $this->to = [''];

        $this->setDocumentSize($size);
        $this->setColor('#000000');
        $this->firstColumnWidth = $this->document['w'] - $this->margins['l'] - $this->margins['r'];

        parent::__construct('P', 'mm', [$this->document['w'], $this->document['h']]);

        $this->AliasNbPages();
        $this->SetMargins($this->margins['l'], $this->margins['t'], $this->margins['r']);
    }

    private function convert($str) {
        return $str ? iconv('UTF-8', self::ICONV_CHARSET_OUTPUT, $str) : '';
    }

    private function setDocumentSize(string $dsize): void
    {
        $dimensions = [
            self::INVOICE_SIZE_LETTER => [215.9, 279.4],
            self::INVOICE_SIZE_LEGAL => [215.9, 355.6],
            self::INVOICE_SIZE_A4 => [210, 297],
        ];

        $this->document = [
            'w' => $dimensions[$dsize][0] ?? 210,
            'h' => $dimensions[$dsize][1] ?? 297,
        ];
    }

    private function resizeToFit($image): array
    {
        [$width, $height] = getimagesize($image);
        $scale = min(
            $this->maxImageDimensions[0] / $width,
            $this->maxImageDimensions[1] / $height
        );

        return [
            round($this->pixelsToMM($scale * $width)),
            round($this->pixelsToMM($scale * $height)),
        ];
    }

    private function pixelsToMM(float $pixels): float
    {
        return $pixels * 25.4 / 96;
    }

    private function hex2rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $length = strlen($hex);

        if ($length === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return array_map('hexdec', str_split($hex, 2));
    }

    private function br2nl(string $string): string
    {
        return str_replace(['<br>', '<br/>', '<br />'], "\n", $string);
    }


    public function setType(string $title): void
    {
        $this->title = $title;
    }

    public function setColor(string $hexColor): void
    {
        $this->color = $this->hex2rgb(ltrim($hexColor, '#'));
    }

    public function setDate(string $date): void
    {
        $this->date = $date;
    }

    public function setTime(string $time): void
    {
        $this->time = $time;
    }

    public function setDue(string $date): void
    {
        $this->due = $date;
    }

    public function setLogo(string $logoPath, int $maxWidth = 0, int $maxHeight = 0): void
    {
        $this->logo = $logoPath;

        if ($maxWidth > 0 && $maxHeight > 0) {
            $this->maxImageDimensions = [$maxWidth, $maxHeight];
        }

        $this->dimensions = $this->resizeToFit($logoPath);
    }

    public function setFrom(array $data): void
    {
        $this->from = $data;
    }

    public function setTo(array $data): void
    {
        $this->to = $data;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function setNumberFormat(
        string $decimals_sep = self::NUMBER_SEPARATOR_DOT,
        string $thousands_sep = self::NUMBER_SEPARATOR_COMMA,
        string $alignment = self::NUMBER_ALIGNMENT_LEFT,
        bool $space = true,
        bool $negativeParenthesis = false
    ): void {
        $this->referenceformat = compact(
            'decimals_sep',
            'thousands_sep',
            'alignment',
            'space',
            'negativeParenthesis'
        );
    }

    public function setFontSizeProductDescription(int $size): void
    {
        $this->fontSizeProductDescription = $size;
    }

    public function price($price, $decimals = 2): string
    {
        $format = $this->referenceformat;
        $number = number_format(abs($price), $decimals, $format['decimals_sep'], $format['thousands_sep']);
        $space = $format['space'] ?? true ? ' ' : '';
        $isRightAligned = ($format['alignment'] ?? self::NUMBER_ALIGNMENT_LEFT) === self::NUMBER_ALIGNMENT_RIGHT;
        $isNegative = $price < 0 && ($format['negativeParenthesis'] ?? false);

        $formattedPrice = $isRightAligned
            ? "{$number}{$space}{$this->currency}"
            : "{$this->currency}{$space}{$number}";

        return $isNegative ? "({$formattedPrice})" : $formattedPrice;
    }

    public function addCustomHeader(string $title, string $content): void
    {
        $this->customHeaders[] = compact('title', 'content');
    }

    public function addItem(string $item, string $description, $quantity = 1,  $price = 0, $discount = 0, $decimals = 2): void
    {
        $p = [
            'item' => $this->convert($item),
            'description' => $this->convert($this->br2nl($description)),
        ];

        $fields = compact('quantity', 'price', 'discount');
        $itemColumns = count($p);

        $discount = 0;
        foreach ($fields as $field => $value) {
            if ($value !== false) {
                if ($field === 'price') {
                    $p[$field] = rtrim($this->price($value / 100, $decimals), '0');
                } elseif ($field === 'discount') {
                    $discount = $value < 1 ? $value : $value / 100;
                    $p[$field] = number_format($discount * 100, 2) . '%';
                } elseif ($field === 'quantity') {
                    $p[$field] = number_format($value, 0);
                } else {
                    $p[$field] = $value;
                }
                $itemColumns++;
            }
        }

        $p['total'] = $this->price($price / 100 * $quantity * (1 - $discount));
        $itemColumns++;

        if (empty($this->items)) {
            $this->columns = $itemColumns;
        } elseif ($itemColumns > $this->columns) {
            $this->firstColumnWidth -= ($itemColumns - $this->columns) * 20;
            $this->columns = $itemColumns;
        }
        $this->firstColumnWidth -= ($itemColumns - 1) * 20;
        $this->items[] = $p;
    }

    public function addTotal(string $name, $value, bool $isInvoiceTotal = false): void
    {
        $value = is_numeric($value) ? $value / 100 : $value;
        $this->totals[] = [
            'name' => $name,
            'value' => is_numeric($value) ? $this->price($value) : $value
        ];

        if ($isInvoiceTotal) {
            $this->invoiceTotal = $value;
        }
    }

    public function addNoticeTitle(string $title): void
    {
        $this->addText[] = ['title', $this->convert(trim($title))];
    }

    public function addNotice(string $paragraph): void
    {
        $this->addText[] = ['paragraph', $this->convert($this->br2nl($paragraph))];
    }

    public function addBadge(string $badge, ?string $color = null): void
    {
        $this->badge = $this->convert($badge);
        $this->badgeColor = $color ? $this->hex2rgb($color) : $this->color;
    }

    public function setFooternote(string $note): void
    {
        $this->footernote = $this->convert(trim($note));
    }

    public function render(string $name = '', string $destination = ''): string
    {
        $this->AddPage();
        $this->Body();
        $this->AliasNbPages();

        return $this->Output($destination, $name);
    }

    public function Header()
    {
        $this->setupHeaderStyle();
        $this->drawLogo();
        $this->drawTitle();

        $this->drawHeaderFields();
        $this->drawCustomHeaders();

        if ($this->PageNo() == 1) {
            $this->printFirstPageInfo();
        }

        $this->finalizeHeader();
    }

    private function setupHeaderStyle()
    {
        $this->SetLineWidth(3);
        $this->Line(0, 0, $this->document['w'], 0);
        $this->SetDrawColor(...$this->color);
        $this->SetLineWidth(0.3);
    }

    private function drawLogo()
    {
        if (!empty($this->logo)) {
            $this->Image(
                $this->logo,
                $this->document['w'] - $this->margins['r'] - $this->dimensions[0],
                $this->margins['t'],
                $this->dimensions[0],
                $this->dimensions[1]
            );
        }
    }

    private function drawTitle()
    {
        if (!empty($this->title)) {
            $this->Ln(2);
            $this->SetFont($this->font, 'B', 18);
            $this->Cell(0, 5, $this->convert($this->title), 0, 1, 'L');
            $this->SetFont($this->font, '', 9);
        }
        $this->Ln(7);
    }

    private function drawHeaderFields()
    {
        $this->SetFont($this->font, 'B', 9);
        $this->printHeaderField('number', $this->convert($this->reference), true);
        $this->printHeaderField('date', $this->convert($this->date));
        if ($this->time) {
            $this->printHeaderField('time', $this->convert($this->time));
        }
        $this->printHeaderField('due', $this->convert($this->due));
        $this->SetFont($this->font, '', 9);
        $this->Ln(-5);
    }

    private function drawCustomHeaders()
    {
        foreach ($this->customHeaders as $customHeader) {
            $this->printHeaderField($this->convert($customHeader['title']), $this->convert($customHeader['content']), true);
        }
    }

    private function finalizeHeader()
    {
        if (!isset($this->productsEnded)) {
            $this->printTableHeader();
        } else {
            $this->Ln(12);
        }
    }

    private function printHeaderField($label, $value, $isBold = false)
    {
        $this->SetFont($this->font, $isBold ? 'B' : '', 9);
        $this->Cell(strlen($this->lang['number']) + 15, 5, $this->lang[$label] . ':', 0, 0, 'L');
        $this->SetFont($this->font, $isBold ? 'B' : '', 9);
        $this->Cell(0, 5, $value, 0, 1, 'L');
    }

    private function printFirstPageInfo()
    {
        $dimensions = $this->dimensions[1] ?? 0;
        $this->SetY(max($this->margins['t'] + $dimensions + 5, $this->GetY() + 10));
        $this->Ln(0);
        $this->SetFillColor(...$this->color);
        $this->SetDrawColor(...$this->color);
        $this->SetFont($this->font, 'B', 10);
        $width = ($this->document['w'] - $this->margins['l'] - $this->margins['r']) / 2;

        $this->SetFont($this->font, 'B', 9);
        $this->Cell($width, 5, $this->convert($this->from[0] ?? ''), 0, 0, 'L');
        $this->Cell(0, 5, $this->convert($this->lang['to'] ?? ''), 0, 0, 'L');
        $this->SetFont($this->font, '', 8);
        $this->Ln(5);

        $maxLines = max(count($this->from ?? []), count($this->to ?? []));
        for ($i = 1; $i < $maxLines; $i++) {
            $this->Cell($width, 5, $this->convert($this->from[$i] ?? ''), 0, 0, 'L');
            $this->Cell(0, 5, $this->convert($this->to[$i - 1] ?? ''), 0, 0, 'L');
            $this->Ln(5);
        }

        $this->Ln(8);
        $this->SetFont($this->font, 'B', 14);
        $this->Cell(0, 5, $this->currency . number_format((float)$this->invoiceTotal, 2) . ' ' . $this->currencyCode . ' due ' . $this->due, 0, 1, 'L');
        $this->SetFont($this->font, '', 9);
    }

    private function printTableHeader()
    {
        $width_other = $this->getOtherColumnsWith();
        $this->Ln(8);
        $this->SetFont($this->font, '', 8);

        $headers = [
            'description' => $this->firstColumnWidth,
            'qty' => $width_other,
            'price' => $width_other,
            'discount' => $width_other,
            'total' => $width_other
        ];

        foreach ($headers as $key => $width) {
            $this->Cell($width, 10, $this->lang[$key], 0, 0, $key === 'description' ? 'L' : 'R', 0);
        }

        $this->Ln(9);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(...$this->color);
        $this->Line($this->margins['l'] + 1, $this->GetY(), $this->document['w'] - $this->margins['r'] - 1, $this->GetY());
        $this->Ln(2);
    }


    public function Body()
    {
        $width_other = $this->getOtherColumnsWith();
        $cellHeight = 8;
        $bgcolor = (1 - $this->columnOpacity) * 255;

        if ($this->items) {
            foreach ($this->items as $item) {
                $this->handleItem($item, $width_other, $cellHeight, $bgcolor);
            }
        }

        $badgeX = $this->getX();
        $badgeY = $this->getY();

        $this->handleTotals($width_other, $cellHeight);

        $this->productsEnded = true;
        $this->Ln(3);

        $this->handleBadge($badgeX, $badgeY);

        $this->handleAdditionalText();
    }

    private function handleItem($item, $width_other, $cellHeight, $bgcolor)
    {
        if (empty($item['item']) || empty($item['description'])) {
            $this->Ln($this->columnSpacing);
        }

        $cHeight = 0;
        if ($item['description']) {
            $cHeight = $this->handleItemDescription($item, $cellHeight);
        }

        $this->drawItemDetails($item, $cellHeight, $bgcolor);
        $this->drawItemFields($item, $width_other, $cHeight + 8);

        $this->Ln();
        $this->Ln($this->columnSpacing);
    }

    private function handleItemDescription($item, $cellHeight)
    {
        $descriptionHeight = $this->calculateDescriptionHeight($this->convert($item['description']), $cellHeight);
        $pageHeight = $this->document['h'] - $this->GetY() - $this->margins['t'] - $this->margins['t'] - $descriptionHeight;
        if ($pageHeight < 1) {
            $this->AddPage();
        }
    }

    private function calculateDescriptionHeight($description, $cellHeight)
    {
        $calculateHeight = new static();
        $calculateHeight->addPage();
        $calculateHeight->setXY(0, 0);
        $calculateHeight->SetFont($this->font, '', 7);
        $calculateHeight->MultiCell($this->firstColumnWidth, 3, $description, 0, 'L', 1);
        return $calculateHeight->getY() + $cellHeight + 2;
    }

    private function drawItemDetails($item, $cellHeight, $bgcolor)
    {
        $cHeight = $cellHeight;
        $this->SetFont($this->font, '', 9);
        $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
        $x = $this->GetX();
        $this->Cell($this->firstColumnWidth, $cHeight, $item['item'], 0, 0, 'L', 0);

        if ($item['description']) {
            $cHeight = $this->drawItemDescription($item, $x, $cHeight);
        }

        return $cHeight;
    }

    private function drawItemDescription($item, $x, $cHeight)
    {
        $resetX = $this->GetX();
        $resetY = $this->GetY();
        $this->SetXY($x, $this->GetY() + (empty($item['item']) ? 3 : 8));
        $this->SetFont($this->font, '', $this->fontSizeProductDescription);
        $this->SetTextColor(100, 100, 100);
        $this->MultiCell($this->firstColumnWidth, floor($this->fontSizeProductDescription / 2), $this->convert($item['description']), 0, 'L', 0);
        $newY = $this->GetY();
        $cHeight = $newY - $resetY + 2;
        $this->SetXY($x, $newY);
        $this->Cell($this->firstColumnWidth, 0, '', 0, 0, 'L', 1);
        $this->SetXY($resetX, $resetY);

        $this->SetTextColor(0, 0, 0);
        return $cHeight;
    }

    private function drawItemFields($item, $width_other, $cHeight = null)
    {
        $cHeight = $cHeight ?? $this->GetY();
        $fields = ['quantity', 'price', 'discount', 'total'];
        foreach ($fields as $field) {
            $this->SetFont($this->font, '', 9);
            $this->Cell($width_other, $cHeight, $item[$field] ?? '', 0, 0, 'R', 1);
        }
    }

    private function handleTotals($width_other, $cellHeight)
    {
        if ($this->totals) {
            $this->Ln(4);
            $this->SetLineWidth(0.2);
            $this->SetDrawColor(235, 235, 235);

            $total_count = count($this->totals);
            foreach ($this->totals as $i => $total) {
                $this->drawTotalLine($total, $i === $total_count - 1, $width_other, $cellHeight);
            }
        }
    }

    private function drawTotalLine($total, $isLast, $width_other, $cellHeight)
    {
        $this->SetFont($this->font, $isLast ? 'b' : '', 9);
        $value = $isLast ? $total['value'] . ' ' . $this->currencyCode : $total['value'];

        $this->Line($this->firstColumnWidth + 12, $this->GetY(), $this->document['w'] - $this->margins['r'] - 1, $this->GetY());
        $this->Ln(1);
        $this->Cell($this->firstColumnWidth, $cellHeight, '', 0, 0, 'L', 0);
        $this->Cell(1, $cellHeight, '', 0, 0, 'L', 1);
        $this->Cell($width_other * 2, $cellHeight, $total['name'], 0, 0, 'L');
        $this->Cell($width_other * 2 - 1, $cellHeight, $value, 0, 0, 'R', 1);
        $this->Ln();
        $this->Ln($this->columnSpacing);
    }

    private function handleBadge($badgeX, $badgeY)
    {
        if ($this->badge) {
            $badge = ' ' . $this->badge . ' ';
            $resetX = $this->getX();
            $resetY = $this->getY();
            $this->setXY($badgeX, $badgeY + 25);
            $this->SetLineWidth(0.4);
            $this->SetDrawColor(220, 55, 45);
            $this->SetTextColor(220, 55, 45);
            $this->SetFont($this->font, 'b', 15);
            $this->Rotate(10, $this->getX(), $this->getY());
            $this->Rect($this->GetX(), $this->GetY(), $this->GetStringWidth($badge) + 2, 10);
            $this->Write(10, $badge);
            $this->Rotate(0);
            $this->setXY($resetX, max($resetY, $this->getY() + 20));
            $this->SetTextColor(0, 0, 0);
        }
    }

    private function handleAdditionalText()
    {
        foreach ($this->addText as $text) {
            if ($text[0] == 'title') {
                $this->SetFont($this->font, 'b', 9);
                $this->Cell(0, 10, $text[1], 0, 0, 'L', 0);
                $this->Ln();
            }
            if ($text[0] == 'paragraph') {
                $this->SetFont($this->font, '', 8);
                $this->MultiCell(0, 4, $text[1], 0, 'L', 0);
                $this->Ln(4);
            }
        }
    }

    public function Footer()
    {
        if (!$this->footernote) {
            $this->footernote = $this->reference . $this->convert(' · ') . $this->currency . number_format((float)$this->invoiceTotal, 2) . ' ' . $this->currencyCode . ' ' . $this->lang['due'] . ' ' . $this->due;
        }
        $this->SetY(-$this->margins['t'] * 2);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(235, 235, 235);
        $this->Line(
            $this->margins['l'] + 1,
            $this->GetY(),
            $this->document['w'] - $this->margins['r'] - 1,
            $this->GetY()
        );

        $this->SetFont($this->font, '', 7);
        $this->Cell(0, 15, $this->footernote, 0, 0, 'L');
        $this->Cell(
            3,
            15,
            "{$this->lang['page']} {$this->PageNo()} {$this->lang['page_of']} {nb}",
            0,
            0,
            'R'
        );
    }

    public function Rotate(float $angle, float $x = -1, float $y = -1): void
    {
        $x = $x === -1 ? $this->x : $x;
        $y = $y === -1 ? $this->y : $y;

        if ($this->angle !== 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle !== 0) {
            $angleRad = deg2rad($angle);
            $c = cos($angleRad);
            $s = sin($angleRad);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;

            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    public function _endpage()
    {
        if ($this->angle !== 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    private function getOtherColumnsWith(): float
    {
        $availableWidth = $this->document['w'] - $this->margins['l'] - $this->margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing);
        return $this->columns === 1 ? $availableWidth : $availableWidth / ($this->columns - 2);
    }
}
