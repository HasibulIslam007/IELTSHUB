<?php

namespace Tests\Unit;

use App\Services\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringTest extends TestCase
{
    public function test_whitespace_and_case_normalize_but_wrong_words_do_not(): void
    {
        $s = new ScoringService;
        $q = ['type' => 'short_answer', 'accepted' => ['green door'], 'max_words' => 2, 'max_numbers' => 0];
        $this->assertTrue($s->mark($q, ' GREEN   door '));
        $this->assertFalse($s->mark($q, 'green doors'));
        $this->assertFalse($s->mark($q, 'the green door'));
        $this->assertFalse($s->mark($q, ''));
    }

    public function test_word_and_number_limits_and_hyphenated_words_are_explicit(): void
    {
        $s = new ScoringService;
        $q = ['type' => 'short_answer', 'accepted' => ['check-in 12'], 'max_words' => 1, 'max_numbers' => 1];
        $this->assertTrue($s->mark($q, 'check-in 12'));
        $q['max_numbers'] = 0;
        $this->assertFalse($s->mark($q, 'check-in 12'));
    }

    public function test_multiple_answers_require_exact_set_without_duplicates(): void
    {
        $s = new ScoringService;
        $q = ['type' => 'multiple_choice', 'accepted' => ['A', 'C'], 'select_count' => 2];
        $this->assertTrue($s->mark($q, ['C', 'A']));
        $this->assertFalse($s->mark($q, ['A']));
        $this->assertFalse($s->mark($q, ['A', 'A']));
        $this->assertFalse($s->mark($q, ['A', 'B']));
    }

    public function test_band_boundaries_require_reviewed_full_test(): void
    {
        $s = new ScoringService;
        $scoring = ['reviewed' => true, 'thresholds' => [['min' => 0, 'band' => 0], ['min' => 23, 'band' => 6], ['min' => 30, 'band' => 7], ['min' => 35, 'band' => 8]]];
        $this->assertSame(6.0, $s->band(29, 40, $scoring));
        $this->assertSame(7.0, $s->band(30, 40, $scoring));
        $this->assertSame(8.0, $s->band(35, 40, $scoring));
        $this->assertNull($s->band(35, 40, ['reviewed' => false]));
        $this->assertNull($s->band(6, 6, $scoring));
    }

    public function test_writing_task_two_has_double_weight_and_half_band_rounding(): void
    {
        $s = new ScoringService;
        $this->assertSame(6.5, $s->assessmentBand('writing', ['task1' => [6, 6, 6, 6], 'task2' => [7, 7, 7, 7]]));
        $this->assertSame(6.5, $s->assessmentBand('speaking',['speaking' => [6, 6, 6, 7]]));
    }
}
