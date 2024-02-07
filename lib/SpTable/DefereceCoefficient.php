<?php

namespace Grade\Report\SpTable;

/**
 * 差異計数の計算
 * Reference: S-P 表の作成と解釈、佐藤隆博著、明治図書、p58
 */
class DefereceCoefficient
{
    const DBM = [
        11 => 0.278,
        12 => 0.285,
        13 => 0.291,
        14 => 0.296,
        15 => 0.302,
        16 => 0.307,
        17 => 0.312,
        18 => 0.317,
        19 => 0.321,
        20 => 0.326,
        21 => 0.330,
        22 => 0.334,
        23 => 0.337,
        24 => 0.341,
        25 => 0.344,
        26 => 0.347,
        27 => 0.350,
        28 => 0.353,
        29 => 0.355,
        30 => 0.358,
        31 => 0.360,
        32 => 0.362,
        33 => 0.364,
        34 => 0.366,
        35 => 0.367,
        36 => 0.369,
        37 => 0.370,
        38 => 0.372,
        39 => 0.373,
        40 => 0.375,
        41 => 0.377,
        42 => 0.378,
        43 => 0.380,
        44 => 0.381,
        45 => 0.382,
    ];

    public $u_scores = [];
    public $q_scores = [];

    /**
     * コンストラクタ
     * 
     * @param array $u_scores 昇順ソートされたユーザごとの成績
     * @param array $q_scores 昇順ソートされた問題ごとの成績
     * @return void
     */
    public function __construct(array $u_scores, array $q_scores)
    {
        $this->u_scores = $u_scores;
        $this->q_scores = $q_scores;
    }

    /**
     * 差異計数を計算
     * 
     * @param void
     * @return float
     */
    public function calc():float
    {
        $numerator = $this->countValuesBetweenLineSandP();
        
        $number_student = $this->countStudents();
        $number_question = $this->countQuestions();
        $average_collect_answer_rate = $this->getAverageScore();
        $m_index = (int) round(sqrt($number_student * $number_question));
        $denominator = 4 * $number_student * $number_question * $average_collect_answer_rate * (1.0 - $average_collect_answer_rate) * $this->getDbmScore($m_index);

        $dc = $numerator / $denominator;

        return $dc;
    }

    public function getDetail():array
    {
        return [
            's_p_area'        => $this->countValuesBetweenLineSandP(),
            'number_student'  => $this->countStudents(),
            'number_question' => $this->countQuestions(),
            'anwer_rate'      => $this->getAverageScore(),
            'm_index'         => (int) round(sqrt($this->countStudents() * $this->countQuestions())),
            'dbm'             => $this->getDbmScore(round(sqrt($this->countStudents() * $this->countQuestions()))),
            'dc'              => $this->calc(),
        ];
    }
    
    /**
     * S曲線とP曲線に挟まれた値（0と1）の数をカウント
     * 
     * @param void
     * @return int
     */
    public function countValuesBetweenLineSandP():int
    {
        $u_cell_counts = $this->countRightCellsLineS();
        $q_cell_counts = $this->countRightCellsLineP();

        $cell_count = 0;
        for ($i = 0; $i < $this->countStudents(); $i++) {
            $cell_count += abs($u_cell_counts[$i] - $q_cell_counts[$i]);
        }

        return $cell_count;
    }

    /**
     * S曲線の右側のセルの数をユーザごとにカウント
     * 
     * @param void
     * @return array
     */
    public function countRightCellsLineS():array
    {
        $q_count = $this->countQuestions();
        $u_cell_counts = array_map(function($v) use($q_count){ return $q_count - $v; }, $this->u_scores);
        sort($u_cell_counts);
        
        return $u_cell_counts;
    }

    /**
     * P曲線の右側のセルの数をユーザごとにカウント
     * 
     * @param void
     * @return array
     */
    public function countRightCellsLineP():array
    {
        $u_count = count($this->u_scores);
        $q_cell_counts = array_fill(0, $u_count, count($this->q_scores));
        foreach ($this->q_scores as $value) {
            for ($i = 0; $i < $u_count; $i++) {
                if ($i < $value) {
                    $q_cell_counts[$i]--;
                }
            }
        }

        return $q_cell_counts;
    }

    private function countStudents():int
    {
        return count($this->u_scores);
    }

    private function countQuestions():int
    {
        return count($this->q_scores);
    }

    private function getDbmScore(int $index):float
    {
        if (empty(self::DBM[$index])) {
            throw new \Exception('Out of DBM range. Can not calcurate DefereceCoefficient.');
        }
        return self::DBM[$index];
    }

    public function getAverageScore():float
    {
        return array_sum($this->u_scores) / ($this->countStudents() * $this->countQuestions());
    }
}