<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample curriculum (modules, lessons, practice files, practice tests, module
 * quizzes, mock exam, final exam) so the course builder can be seen working.
 * Everything is placeholder content to edit or replace in Admin > Courses >
 * Curriculum. Run: php artisan db:seed --class=SampleCurriculumSeeder
 */
class SampleCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $this->prepareOnlineSale();

        $course = Course::where('slug', 'basic-excel')->first() ?? Course::orderBy('sort_order')->first();
        if (! $course) {
            $this->command?->warn('No course found - import or create a course first.');

            return;
        }
        if ($course->modules()->exists() || $course->lessons()->exists()) {
            $this->command?->warn("“{$course->title}” already has a curriculum - sample not added.");

            return;
        }

        DB::transaction(function () use ($course) {
            $sumVideo = $this->sumTutorialVideo();

            // Module 1
            $m1 = $course->modules()->create(['title' => 'Getting started with Excel', 'description' => 'The Excel window, workbooks and worksheets, and entering data.', 'position' => 1]);
            $this->lesson($course, $m1, 1, 'Welcome: what you will learn', '3:00', true, null, $this->notes(
                'Sample lesson - replace this text in the admin.',
                'In this course you will learn to enter data, write formulas, use the most common functions and build tidy, printable worksheets.',
                ['Watch each lesson and practise in your own copy of Excel.', 'Download the practice workbook when a lesson has one.', 'Pass the module quizzes and the final exam to get your certificate.']
            ));
            $this->lesson($course, $m1, 2, 'The Excel window: ribbon, cells and sheets', '6:30', false, null, $this->notes(
                'Sample lesson.',
                'A workbook is one Excel file. It holds worksheets (tabs at the bottom). Each worksheet is a grid of cells named by column letter and row number, such as C5.',
                ['The Name Box shows the address of the selected cell.', 'The Formula Bar shows what is really inside the cell.', 'The ribbon groups commands into tabs: Home, Insert, Formulas, Data…']
            ));
            $this->lesson($course, $m1, 3, 'Entering and editing data', '8:00', false, null, $this->notes(
                'Sample lesson with a practice file.',
                'Type in a cell and press Enter to go down or Tab to go right. Press Esc to cancel. Double-click a cell or press F2 to edit it.',
                ['Open the practice workbook.', 'Fill in the missing quantities.', 'Compare with the solution file.']
            ), 'sales-practice.csv', 'sales-solution.csv');

            $this->quiz($course, $m1, 4, 'practice', 'Practice: Excel basics', [
                ['single', 'Which part of the Excel window shows the address of the selected cell?', ['Name Box' => true, 'Formula Bar' => false, 'Status Bar' => false, 'Ribbon' => false], 'The Name Box, at the left of the formula bar, shows addresses such as B3.'],
                ['true_false', 'A workbook can contain more than one worksheet.', true, 'Add sheets with the + button next to the sheet tabs.'],
                ['single', 'What is the address of the cell in column C, row 5?', ['C5' => true, '5C' => false, 'R5C3' => false, 'C:5' => false], 'Column letter first, then row number.'],
                ['multiple', 'Which of these let you edit the contents of a cell?', ['Double-click the cell' => true, 'Select it and press F2' => true, 'Select it and type in the Formula Bar' => true, 'Right-click the sheet tab and choose Delete' => false], 'Deleting a sheet removes the whole worksheet - it does not edit a cell.'],
            ]);
            $this->quiz($course, $m1, 5, 'quiz', 'Module 1 quiz', [
                ['single', 'A workbook is…', ['An Excel file that holds one or more worksheets' => true, 'A single cell' => false, 'One row of data' => false, 'A chart' => false], null],
                ['single', 'Which key confirms an entry and moves to the cell below?', ['Enter' => true, 'Tab' => false, 'Esc' => false, 'Shift' => false], 'Tab moves to the right; Esc cancels.'],
                ['true_false', 'Pressing Esc while typing in a cell cancels the entry.', true, null],
                ['single', 'Which shortcut undoes the last action?', ['Ctrl + Z' => true, 'Ctrl + Y' => false, 'Ctrl + U' => false, 'Ctrl + X' => false], 'Ctrl + Y redoes; Ctrl + X cuts.'],
                ['multiple', 'What can a cell contain?', ['Text' => true, 'Numbers' => true, 'Formulas' => true, 'A whole worksheet' => false], null],
            ]);

            // Module 2
            $m2 = $course->modules()->create(['title' => 'Formulas and functions', 'description' => 'Write formulas, use SUM, AVERAGE, MIN and MAX, and copy formulas correctly.', 'position' => 2]);
            $this->lesson($course, $m2, 1, 'Your first formula', '7:00', false, null, $this->notes(
                'Sample lesson.',
                'Every formula starts with an equals sign. =A1+B1 adds the values in A1 and B1, and the answer updates when those cells change.',
                ['Use + - * / for add, subtract, multiply and divide.', 'Point at cells with the mouse instead of typing addresses.', 'Press Enter to finish the formula.']
            ));
            $this->lesson($course, $m2, 2, 'SUM, AVERAGE, MIN and MAX', '10:00', false, $sumVideo, $this->notes(
                'Sample lesson (video from your SUM tutorial).',
                'Functions do common calculations for you. They take a range such as B2:B10.',
                ['=SUM(B2:B10) adds the numbers.', '=AVERAGE(B2:B10) returns the mean.', '=MIN(...) and =MAX(...) return the smallest and largest value.']
            ));
            $this->lesson($course, $m2, 3, 'Relative and absolute references', '9:00', false, null, $this->notes(
                'Sample lesson with a practice file.',
                'When you copy a formula, its cell references move with it (relative). Put $ in front of the column or row to lock it: $A$1 never moves, A$1 locks only the row.',
                ['Calculate each line total with =B2*C2 and copy it down.', 'Add a tax rate in one cell and refer to it with $F$1.', 'Check your result against the solution.']
            ), 'references-practice.csv', 'references-solution.csv');

            $this->quiz($course, $m2, 4, 'practice', 'Practice: formulas', [
                ['single', 'What does =A1+B1 do?', ['Adds the values in A1 and B1' => true, 'Joins A1 and B1 as text' => false, 'Copies A1 into B1' => false, 'Nothing - it is just text' => false], null],
                ['single', 'Which function adds up a range of numbers?', ['SUM' => true, 'COUNT' => false, 'MAX' => false, 'ROUND' => false], null],
                ['true_false', '=AVERAGE(B2:B6) returns the average of the numbers in B2 to B6.', true, null],
                ['single', 'In $A$1, what do the dollar signs do?', ['Keep the column and row fixed when the formula is copied' => true, 'Format the cell as currency' => false, 'Make the cell read-only' => false, 'Hide the cell' => false], 'Dollar signs make a reference absolute; they have nothing to do with money.'],
            ]);
            $this->quiz($course, $m2, 5, 'quiz', 'Module 2 quiz', [
                ['single', 'Which formula returns the largest value in C2:C20?', ['=MAX(C2:C20)' => true, '=MIN(C2:C20)' => false, '=SUM(C2:C20)' => false, '=TOP(C2:C20)' => false], null],
                ['single', 'You copy =B2*C2 from row 2 to row 3. What does it become?', ['=B3*C3' => true, '=B2*C2' => false, '=B3*C2' => false, '=C3*D3' => false], 'Relative references move one row down.'],
                ['single', '=COUNT(A1:A10) counts the cells that…', ['contain numbers' => true, 'are not empty' => false, 'are empty' => false, 'contain text' => false], 'COUNTA counts every non-empty cell.'],
                ['true_false', 'A$1 keeps the row fixed but lets the column change when copied across.', true, null],
                ['multiple', 'Which of these are correct uses of SUM?', ['=SUM(A1:A10)' => true, '=SUM(A1,A3,A5)' => true, '=SUM(A1:A10,C1)' => true, '=SUM A1:A10' => false], 'Arguments go inside brackets, separated by commas.'],
            ]);

            // Final assessment
            $m3 = $course->modules()->create(['title' => 'Final assessment', 'description' => 'Rehearse with the timed mock exam, then pass the final exam to get your certificate.', 'position' => 3]);
            $this->quiz($course, $m3, 1, 'mock', 'Mock exam', [
                ['single', 'Which shortcut saves the workbook?', ['Ctrl + S' => true, 'Ctrl + P' => false, 'Ctrl + N' => false, 'Ctrl + W' => false], null],
                ['single', 'What is the result of =10/4?', ['2.5' => true, '2' => false, '4' => false, '#DIV/0!' => false], null],
                ['single', 'Which error appears when a formula divides by zero?', ['#DIV/0!' => true, '#N/A' => false, '#NAME?' => false, '#REF!' => false], null],
                ['true_false', '=SUM(A1:A3) and =A1+A2+A3 give the same result when A1:A3 hold numbers.', true, null],
                ['single', 'What does #NAME? usually mean?', ['Excel does not recognise a function or name in the formula' => true, 'The column is too narrow' => false, 'A number was divided by zero' => false, 'The cell is empty' => false], 'Check the spelling of the function.'],
                ['multiple', 'A1 = 5 and A2 = 10. Which formulas return 15?', ['=A1+A2' => true, '=SUM(A1:A2)' => true, '=A1*3' => true, '=A2-A1' => false], '=A2-A1 returns 5.'],
                ['single', 'Which of these is an absolute reference?', ['$B$2' => true, 'B2' => false, 'B$2' => false, '$B2' => false], 'B$2 and $B2 are mixed references.'],
                ['true_false', 'In Excel, columns are labelled with numbers and rows with letters.', false, 'Columns use letters (A, B, C…) and rows use numbers.'],
            ], ['time_limit' => 15, 'shuffle_questions' => true]);
            $this->quiz($course, $m3, 2, 'final', 'Final exam', [
                ['single', 'Which shortcut copies the selected cells?', ['Ctrl + C' => true, 'Ctrl + V' => false, 'Ctrl + X' => false, 'Ctrl + A' => false], null],
                ['single', 'What does =MIN(4, 9, 2) return?', ['2' => true, '4' => false, '9' => false, '15' => false], null],
                ['single', 'A cell shows #####. What is the most likely cause?', ['The column is too narrow for the number' => true, 'The formula has an error' => false, 'The cell is locked' => false, 'The sheet is protected' => false], 'Widen the column to see the value.'],
                ['true_false', 'AVERAGE ignores empty cells in its range.', true, null],
                ['single', '=ROUND(3.456, 1) returns…', ['3.5' => true, '3.4' => false, '3.46' => false, '3' => false], null],
                ['multiple', 'Which of these are mixed references?', ['A$1' => true, '$A1' => true, '$A$1' => false, 'A1' => false], null],
                ['single', 'You need the number of non-empty cells in A1:A50, text included. Which function?', ['COUNTA' => true, 'COUNT' => false, 'SUM' => false, 'COUNTBLANK' => false], null],
                ['single', 'Which formula is written correctly?', ['=SUM(B2:B9)' => true, 'SUM(B2:B9)' => false, '=SUM B2:B9' => false, '=SUM(B2:B9' => false], 'A formula needs the = sign and matching brackets.'],
            ], ['time_limit' => 20, 'max_attempts' => 3, 'pass_percent' => 75, 'shuffle_questions' => true]);
        });

        $this->command?->info("Sample curriculum added to “{$course->title}”.");
    }

    /** Give courses without a plan one, from the imported price box ("$49" "Per year"). */
    private function prepareOnlineSale(): void
    {
        foreach (Course::doesntHave('plans')->where('is_free', false)->get() as $course) {
            if (! preg_match('/(\d+(?:\.\d+)?)/', (string) $course->price, $m)) {
                continue;
            }
            $suffix = mb_strtolower((string) $course->price_suffix);
            $course->plans()->create([
                'price' => (float) $m[1],
                'days' => match (true) {
                    str_contains($suffix, 'year') => 365,
                    str_contains($suffix, 'month') => 30,
                    default => null,
                },
                'position' => 1,
            ]);
        }
    }

    private function lesson(Course $course, $module, int $position, string $title, string $duration, bool $preview, ?string $video, string $body, ?string $practice = null, ?string $solution = null): void
    {
        $lesson = $course->lessons()->create([
            'course_module_id' => $module->id,
            'title' => $title,
            'slug' => \App\Support\Slug::make($title),
            'position' => $position,
            'duration' => $duration,
            'video_url' => $video,
            'body' => $body,
            'is_preview' => $preview,
            'is_published' => true,
        ]);

        if ($practice) {
            $lesson->update([
                'attachment_path' => $this->csv($course, $practice, false),
                'attachment_name' => $practice,
                'solution_path' => $this->csv($course, $solution, true),
                'solution_name' => $solution,
            ]);
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: array<string, bool>|bool, 3: ?string}>  $questions
     */
    private function quiz(Course $course, $module, int $position, string $type, string $title, array $questions, array $settings = []): void
    {
        $quiz = $course->quizzes()->create(array_merge([
            'course_module_id' => $module->id,
            'type' => $type,
            'title' => $title,
            'slug' => \App\Support\Slug::make($title),
            'description' => 'Sample test - edit or replace the questions in the admin.',
            'position' => $position,
            'pass_percent' => 70,
            'show_answers' => true,
            'is_published' => true,
        ], $settings));

        foreach ($questions as $i => [$qType, $prompt, $answers, $explanation]) {
            $question = $quiz->questions()->create([
                'type' => $qType,
                'prompt' => $prompt,
                'explanation' => $explanation,
                'points' => 1,
                'position' => $i + 1,
            ]);
            $options = $qType === 'true_false'
                ? [__('account.true', [], 'en') => $answers === true, __('account.false', [], 'en') => $answers === false]
                : $answers;
            $n = 0;
            foreach ($options as $text => $correct) {
                $question->options()->create(['text' => (string) $text, 'is_correct' => $correct, 'position' => ++$n]);
            }
        }
    }

    private function notes(string $badge, string $intro, array $steps): string
    {
        return '<p><em>'.e($badge).'</em></p><p>'.e($intro).'</p><ul><li>'.implode('</li><li>', array_map('e', $steps)).'</li></ul>';
    }

    /** Small CSV practice / solution workbooks (open in Excel). */
    private function csv(Course $course, string $name, bool $solved): string
    {
        $rows = str_starts_with($name, 'sales')
            ? [['Product', 'Qty', 'Price', 'Total'], ['Notebook', 12, 1.5], ['Pen', 40, 0.25], ['Stapler', 5, 4.0], ['Folder', 20, 0.8]]
            : [['Item', 'Qty', 'Unit price', 'Line total', 'Tax rate', '10%'], ['Mouse', 3, 8], ['Keyboard', 2, 15], ['Monitor', 1, 120], ['Cable', 6, 2.5]];

        $lines = [];
        foreach ($rows as $i => $row) {
            if ($i > 0) {
                $row[] = $solved ? (str_starts_with($name, 'sales') ? '=B'.($i + 1).'*C'.($i + 1) : '=B'.($i + 1).'*C'.($i + 1).'*(1+$F$1)') : '';
            }
            $lines[] = implode(',', $row);
        }
        if ($solved) {
            $lines[] = 'Total,,,=SUM(D2:D'.count($rows).')';
        }

        $path = 'lessons/'.$course->id.'/'.$name;
        Storage::disk('local')->put($path, "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n");

        return $path;
    }

    /** YouTube link of the imported SUM tutorial, if there is one. */
    private function sumTutorialVideo(): ?string
    {
        $body = (string) Article::ofType('tutorial')->where('slug', 'how-to-use-sum-function')->value('body');

        return preg_match('~src="(https://www\.youtube(?:-nocookie)?\.com/embed/[\w-]{11})~', $body, $m) ? $m[1] : null;
    }
}
