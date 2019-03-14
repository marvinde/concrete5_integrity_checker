<?php
namespace Concrete5IntegrityChecker;

class DiffChecker
{
    /**
     * @var int
     */
    const UNMODIFIED = 0;

    /**
     * @var int
     */
    const DELETED = 1;

    /**
     * @var int
     */
    const INSERTED = 2;

    /**
     * @var array
     */
    protected $diff = [];

    /**
     * @param string $file1
     * @param string $file2
     */
    public function __construct(
        $file1,
        $file2
    ) {
        $this->diff = [];
        $string1 = file_get_contents($file1);
        $string2 = file_get_contents($file2);
        $start = 0;
        $sequence1 = preg_split('/\R/', $string1);
        $sequence2 = preg_split('/\R/', $string2);
        $end1 = count($sequence1) - 1;
        $end2 = count($sequence2) - 1;
        while ($start <= $end1 && $start <= $end2 && $sequence1[$start] == $sequence2[$start]) {
            $start++;
        }
        while ($end1 >= $start && $end2 >= $start && $sequence1[$end1] == $sequence2[$end2]) {
            $end1--;
            $end2--;
        }
        $length1 = $end1 - $start + 1;
        $length2 = $end2 - $start + 1;
        $table = [array_fill(0, $length2 + 1, 0)];
        for ($index1 = 1; $index1 <= $length1; $index1++) {
            $table[$index1] = [0];
            for ($index2 = 1; $index2 <= $length2; $index2++) {
                if ($sequence1[$index1 + $start - 1] == $sequence2[$index2 + $start - 1]) {
                    $table[$index1][$index2] = $table[$index1 - 1][$index2 - 1] + 1;
                } else {
                    $table[$index1][$index2] = max($table[$index1 - 1][$index2], $table[$index1][$index2 - 1]);
                }
            }
        }
        $partialDiff = [];
        $index1 = count($table) - 1;
        $index2 = count($table[0]) - 1;
        while ($index1 > 0 || $index2 > 0) {
            if ($index1 > 0 && $index2 > 0 && $sequence1[$index1 + $start - 1] == $sequence2[$index2 + $start - 1]) {
                $partialDiff[] = [$sequence1[$index1 + $start - 1], self::UNMODIFIED];
                $index1--;
                $index2--;
            } elseif ($index2 > 0 && $table[$index1][$index2] == $table[$index1][$index2 - 1]) {
                $partialDiff[] = [$sequence2[$index2 + $start - 1], self::INSERTED];
                $index2--;
            } else {
                $partialDiff[] = [$sequence1[$index1 + $start - 1], self::DELETED];
                $index1--;
            }
        }
        for ($index = 0; $index < $start; $index++) {
            $this->diff[] = [$sequence1[$index], self::UNMODIFIED];
        }
        while (count($partialDiff) > 0) {
            $this->diff[] = array_pop($partialDiff);
        }
        for ($index = $end1 + 1; $index < count($sequence1); $index++) {
            $this->diff[] = [$sequence1[$index], self::UNMODIFIED];
        }
    }

    /**
     * @return string
     */
    public function output()
    {
        $html = '';
        $currentLineNumber = 0;
        $lastDeletedLineNumber = null;
        foreach ($this->diff as $key =>  $line) {
            switch ($line[1]) {
                case self::UNMODIFIED:
                    $element = 'span';
                    $sign = '   ';
                    $currentLineNumber++;
                    break;
                case self::DELETED:
                    $element = 'del';
                    $sign = ' - ';
                    if ($this->diff[$key + 1][1] !== self::UNMODIFIED) {
                        $currentLineNumber++;
                    }
                    if ($lastDeletedLineNumber === null) {
                        $lastDeletedLineNumber = $currentLineNumber;
                    }
                    break;
                case self::INSERTED:
                    $element = 'ins';
                    $sign = ' + ';
                    if ($this->diff[$key - 1][1] === self::DELETED) {
                        $currentLineNumber = $lastDeletedLineNumber;
                    } else {
                        $currentLineNumber++;
                    }
                    if ($this->diff[$key + 1][1] === self::UNMODIFIED) {
                        $lastDeletedLineNumber = null;
                    }
                    break;
            }

            $displayLine = true;
            if ($line[1] === self::UNMODIFIED) {
                $displayLine = false;
                for ($i = 1; $i <= 3; $i++) {
                    $nextLineType = $this->diff[$key + $i][1];
                    $previousLineType = $this->diff[$key - $i][1];
                    if ($nextLineType != self::UNMODIFIED || $previousLineType != self::UNMODIFIED) {
                        $displayLine = true;
                        break;
                    }
                }
            }

            if ($displayLine) {
                $html .= '<' . $element . ' class="diff-element"><span class="line-number">' . $currentLineNumber . '</span>' . $sign . htmlspecialchars($line[0]) . '</' . $element . '>';
            } else {
                $html .= '<span class="diff-element no-changes"></span>';
            }
        }

        return $html;
    }
}
