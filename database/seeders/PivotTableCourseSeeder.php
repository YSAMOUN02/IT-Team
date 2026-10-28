<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Support\DocxReader;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * A complete course built from the training manual in example/:
 * "ការប្រើប្រាស Pivot Table.docx" (lessons and screenshots) and
 * "PivotTable_365_Practice (2).xlsx" (practice workbook and exercise list).
 * Modules, 19 lessons, module tests, a mock exam and the final exam for the
 * certificate. Real content: it is not removed by demo:clear.
 *
 * Run: php artisan db:seed --class=PivotTableCourseSeeder
 */
class PivotTableCourseSeeder extends Seeder
{
    private const SLUG = 'pivot-table';

    private const IMAGE_FOLDER = 'courses/pivot-table';

    private const MANUAL = 'example/ការប្រើប្រាស Pivot Table.docx';

    private const WORKBOOK = 'example/PivotTable_365_Practice (2).xlsx';

    private const WORKBOOK_NAME = 'PivotTable_365_Practice.xlsx';

    /** @var array<string, ?string> picture in the manual => saved URL */
    private array $saved = [];

    public function run(): void
    {
        if (Course::where('slug', self::SLUG)->exists()) {
            $this->command?->warn('The Pivot Table course already exists. Delete it in Admin > Courses first to import it again.');

            return;
        }
        foreach ([self::MANUAL, self::WORKBOOK] as $file) {
            if (! is_file(base_path($file))) {
                throw new RuntimeException("Missing source file: $file");
            }
        }

        $doc = new DocxReader(base_path(self::MANUAL));
        $blocks = $doc->blocks();
        foreach ($blocks as &$block) {
            $block['images'] = array_values(array_filter(array_map(fn ($img) => $this->saveImage($doc, $img), $block['images'])));
        }
        unset($block);

        DB::transaction(function () use ($blocks, $doc) {
            $course = $this->course($doc);
            $lesson = fn (CourseModule $module, int $position, string $title, int $from, int $to, array $extra = []) => $this->lesson($course, $module, $position, $title, array_slice($blocks, $from, $to - $from), $extra);

            // 1. Getting started
            $m = $course->modules()->create(['position' => 1, 'title' => 'ស្គាល់ PivotTable', 'description' => 'PivotTable ជាអ្វី ពេលណាគួរប្រើ របៀបរៀបចំទិន្នន័យដើម និងបង្កើត PivotTable ដំបូងរបស់អ្នក។']);
            $lesson($m, 1, 'PivotTable ជាអ្វី? ហេតុអ្វីត្រូវប្រើ?', 1, 11, ['is_preview' => true]);
            $lesson($m, 2, 'រៀបចំទិន្នន័យដើម និងបង្កើត PivotTable', 12, 26, [
                'intro' => '<p><strong>មុននឹងចាប់ផ្តើម៖</strong> ទាញយក File អនុវត្ត <strong>'.self::WORKBOOK_NAME.'</strong> នៅខាងក្រោមមេរៀននេះ។ Sheet <strong>Pivot Table</strong> មានទិន្នន័យលក់ពិតចំនួន ៣៧,៨៦១ ជួរ (2019–2023) ដែលយើងប្រើពេញមួយវគ្គសិក្សា។</p>',
                'practice' => true,
            ]);
            $lesson($m, 3, 'Fields ទាំង ៤៖ Filters, Columns, Rows និង Values', 26, 32);
            $this->quiz($course, $m, 4, 'practice', 'លំហាត់សាកល្បង៖ មូលដ្ឋាន PivotTable', [
                ['single', 'PivotTable ប្រើសម្រាប់ធ្វើអ្វី?', ['សង្ខេប រៀបចំ វិភាគ និងប្រៀបធៀបទិន្នន័យធំៗ ដោយមិនចាំបាច់សរសេរ Formula ច្រើន' => true, 'គូររូបភាព និងតុបតែងពណ៌ក្រឡា' => false, 'ដាក់ Password ការពារ File' => false, 'ផ្ញើ Email ចេញពី Excel' => false], null],
                ['single', 'ដើម្បីបង្កើត PivotTable យើងចុចលើ Cell ណាមួយក្នុងតារាងទិន្នន័យ រួចចូលទៅ Tab ណា?', ['Insert' => true, 'Home' => false, 'Page Layout' => false, 'View' => false], 'Tab Insert → PivotTable។'],
                ['true_false', 'ទិន្នន័យដើម (Raw Data) អាចមានជួរដេក ឬជួរឈរទទេរនៅកណ្តាលតារាងបាន ដោយគ្មានបញ្ហាអ្វីឡើយ។', false, 'ត្រូវប្រាកដថាគ្មានជួរទទេរនៅកណ្តាលតារាង ហើយ Column នីមួយៗមានចំណងជើង (Header) ច្បាស់លាស់។'],
                ['single', 'Field ដែលមានទិន្នន័យជាលេខ ហើយត្រូវគណនាបូកសរុប (ឧ. ទឹកប្រាក់លក់) គួរអូសទៅតំបន់ណា?', ['Values' => true, 'Rows' => false, 'Columns' => false, 'Filters' => false], null],
                ['single', 'ពេលបង្កើត PivotTable ហើយជ្រើសរើស New Worksheet មានន័យថា…', ['PivotTable ត្រូវបានបង្កើតនៅលើ Worksheet ថ្មីមួយ' => true, 'PivotTable ត្រូវដាក់លើ Worksheet ដែលមានស្រាប់' => false, 'Excel នឹងលុប Worksheet ចាស់ចោល' => false, 'Excel នឹងបង្កើត File ថ្មី' => false], null],
                ['multiple', 'ករណីណាខ្លះដែលគួរប្រើ PivotTable?', ['មានទិន្នន័យរាប់ពាន់ជួរ ហើយចង់មើលជាលក្ខណៈសង្ខេប' => true, 'ចង់បូកសរុប ឬរាប់ចំនួនតាមប្រភេទនីមួយៗ' => true, 'ចង់រៀបចំរបាយការណ៍សង្ខេបជូនថ្នាក់ដឹកនាំ' => true, 'ចង់វាយលិខិតផ្លូវការ' => false], null],
            ], ['description' => 'លំហាត់សាកល្បង៖ ឆ្លើយហើយឃើញចម្លើយត្រឹមត្រូវភ្លាមៗ។ មិនរាប់ពិន្ទុទេ។']);

            // 2. PivotTable Design
            $m = $course->modules()->create(['position' => 2, 'title' => 'Tab PivotTable Design', 'description' => 'ប្តូរទម្រង់បង្ហាញ ផលបូក Subtotal / Grand Total និងពណ៌តារាង ដោយប្រើ Tab PivotTable Design។']);
            $lesson($m, 1, 'Layout៖ Subtotals, Grand Totals, Report Layout និង Blank Rows', 32, 60);
            $lesson($m, 2, 'PivotTable Style Options និង PivotTable Styles', 60, 70);
            $this->quiz($course, $m, 3, 'quiz', 'តេស្ត Module 2៖ Tab PivotTable Design', [
                ['single', 'Report Layout មួយណាដែលគួរជ្រើសរើស ពេលអ្នកចង់ចម្លងទិន្នន័យពី PivotTable ទៅប្រើប្រាស់បន្ត?', ['Show in Tabular Form' => true, 'Show in Compact Form' => false, 'Show in Outline Form' => false, 'Insert Blank Line after Each Item' => false], 'Tabular Form បង្ហាញជាតារាងបុរាណ ដែលស្រួលចម្លងទៅប្រើបន្ត។'],
                ['single', 'Show in Compact Form គឺជា…', ['ទម្រង់ដើមរបស់ Excel ដែលបង្រួមតូច ងាយមើលលើអេក្រង់' => true, 'ទម្រង់ដែលបំបែកជួរឈរតាម Field នីមួយៗ' => false, 'ទម្រង់ដែលលុបផលបូកសរុបទាំងអស់' => false, 'ទម្រង់សម្រាប់ព្រីនតែប៉ុណ្ណោះ' => false], null],
                ['single', 'ជម្រើស Grand Totals មួយណាដែលបិទផលបូកសរុបទាំងជួរដេក និងជួរឈរ?', ['Off for Rows and Columns' => true, 'On for Rows and Columns' => false, 'On for Rows Only' => false, 'On for Columns Only' => false], null],
                ['single', 'Repeat All Item Labels ធ្វើអ្វី?', ['បំពេញឈ្មោះដែលស្ទួនជាន់គ្នាដោយស្វ័យប្រវត្តិ' => true, 'លុបឈ្មោះស្ទួនចោល' => false, 'បញ្ចូលជួរទទេរចន្លោះក្រុម' => false, 'ប្តូរពណ៌តារាង' => false], null],
                ['true_false', 'Banded Rows ដាក់ពណ៌ឆ្នូតឆ្លាស់ជួរដេក ដើម្បីឱ្យងាយមើលជួរនីមួយៗ មិនច្រឡំ។', true, null],
                ['single', 'ចង់លាក់ផលបូកតាមក្រុមតូចៗ (Subtotal) យើងជ្រើសរើស…', ['Subtotals → Do Not Show Subtotals' => true, 'Grand Totals → On for Rows Only' => false, 'Blank Rows → Remove Blank Line after Each Item' => false, 'Report Layout → Show in Outline Form' => false], null],
            ]);

            // 3. PivotTable Options
            $m = $course->modules()->create(['position' => 3, 'title' => 'PivotTable Options', 'description' => 'ការកំណត់ស៊ីជម្រៅក្នុងផ្ទាំង PivotTable Options៖ Layout & Format, Totals & Filters, Display, Printing និង Data។']);
            $lesson($m, 1, 'Tab PivotTable Analyze៖ PivotTable Name និង Layout & Format', 70, 95);
            $lesson($m, 2, 'Tab Totals & Filters', 95, 118);
            $lesson($m, 3, 'Tab Display', 118, 143);
            $lesson($m, 4, 'Tab Printing និង Tab Data', 143, 170);
            $this->quiz($course, $m, 5, 'quiz', 'តេស្ត Module 3៖ PivotTable Options', [
                ['single', 'ជម្រើសណាដែលរក្សាពណ៌ ម៉ូតអក្សរ និងទម្រង់ប្រាក់ ($) ដែលបានរចនារួច ទោះបីចុច Refresh ក៏ដោយ?', ['Preserve cell formatting on update' => true, 'Autofit column widths on update' => false, 'For empty cells show' => false, 'Merge and center cells with labels' => false], null],
                ['single', 'ចង់ឱ្យក្រឡាទទេរបង្ហាញលេខ 0 ឬ - ជំនួសការទុកទំនេរ គួរប្រើ…', ['For empty cells show' => true, 'For error values show' => false, 'Show grand totals for rows' => false, 'Sort A to Z' => false], null],
                ['true_false', 'Allow multiple filters per field អនុញ្ញាតឱ្យប្រើ Label Filter និង Value Filter លើ Field តែមួយក្នុងពេលតែមួយ។', true, 'បើដកគ្រីស ការដាក់ Filter ថ្មីនឹងលុប Filter ចាស់ចោល។'],
                ['single', 'បើគ្រីស Use Custom Lists when sorting ឈ្មោះខែ (Jan, Feb, Mar…) នឹងតម្រៀបតាម…', ['លំដាប់ខែតាមប្រតិទិន (Custom List)' => true, 'លំដាប់អក្សរក្រម A-Z' => false, 'តម្លៃលេខពីធំទៅតូច' => false, 'លំដាប់ចៃដន្យ' => false], null],
                ['single', 'Enable show details អនុញ្ញាតឱ្យយើង…', ['Double-click លើលេខ ដើម្បីបង្កើត Sheet ថ្មីបង្ហាញទិន្នន័យលម្អិតនៃលេខនោះ' => true, 'បង្ហាញ Tooltips ពេលដាក់ Mouse ពីលើ Cell' => false, 'ព្រីនសញ្ញា + / -' => false, 'លាក់ Field Headers' => false], null],
                ['single', 'Number of items to retain per field គួរជ្រើសរើស None ដើម្បី…', ['លុបឈ្មោះចាស់ៗដែលលែងមានក្នុងទិន្នន័យដើម ចេញពីបញ្ជី Filter ក្រោយ Refresh' => true, 'រក្សាឈ្មោះចាស់ៗទាំងអស់ទុក' => false, 'បិទមុខងារ Refresh' => false, 'ធ្វើឱ្យ File ធំជាងមុន' => false], null],
                ['true_false', 'Repeat row labels on each printed page បង្ហាញ Row Labels នៅផ្នែកខាងលើនៃគ្រប់ទំព័រដែលបានព្រីន។', true, null],
            ]);

            // 4. Field settings
            $m = $course->modules()->create(['position' => 4, 'title' => 'Active Field និង Value Field Settings', 'description' => 'កំណត់ Field នីមួយៗ ជ្រើសរើសប្រភេទគណនា ទម្រង់លេខ និងបង្ហាញតម្លៃជាភាគរយ។']);
            $lesson($m, 1, 'Active Field និង Field Settings', 170, 208);
            $lesson($m, 2, 'Value Field Settings៖ Summarize Values By', 208, 225);
            $lesson($m, 3, 'Number Format និង Show Values As (%)', 225, 241);
            $this->quiz($course, $m, 4, 'quiz', 'តេស្ត Module 4៖ Field Settings', [
                ['single', 'Summarize Values By មួយណាដែលរាប់ចំនួនទិន្នន័យ ដោយរាប់តម្លៃដែលស្ទួនគ្នាត្រឹមតែ ១ ដង?', ['Distinct Count' => true, 'Count' => false, 'Count Numbers' => false, 'Sum' => false], null],
                ['single', 'Count Numbers រាប់…', ['តែក្រឡាដែលមានតម្លៃជាលេខសុទ្ធ' => true, 'គ្រប់ក្រឡាដែលមិនទទេរ' => false, 'តែក្រឡាទទេរ' => false, 'តែក្រឡាដែលមានអក្សរ' => false], 'វារំលងក្រឡាដែលមានអក្សរ ឬទទេ។'],
                ['single', '% of Grand Total បង្ហាញតម្លៃក្នុង Cell នីមួយៗជាភាគរយ ធៀបនឹង…', ['ផលបូករួមទាំងអស់ (Grand Total = 100%)' => true, 'ផលបូកសរុបតាមជួរឈរ' => false, 'ផលបូកសរុបតាមជួរដេក' => false, 'ក្រុមធំតាមជួរដេក' => false], null],
                ['single', 'ចង់បង្ហាញភាគរយ ធៀបនឹងផលបូកសរុបតាមជួរឈរនីមួយៗ គួរជ្រើសរើស…', ['% of Column Total' => true, '% of Row Total' => false, '% of Grand Total' => false, '% of Parent Row Total' => false], null],
                ['multiple', 'ប៊ូតុង Number Format អាចធ្វើអ្វីបាន?', ['ដាក់សញ្ញា Currency ($, ៛)' => true, 'ដាក់សញ្ញាក្បៀសបំបែកខ្ទង់ពាន់ (,)' => true, 'កំណត់ចំនួនខ្ទង់ទសភាគ' => true, 'ផ្លាស់ Field ពី Rows ទៅ Columns' => false], null],
                ['single', 'ក្នុង Field Settings ការជ្រើសរើស Subtotals = None មានន័យថា…', ['លាក់ Subtotal ទាំងអស់សម្រាប់ Field នោះ' => true, 'Excel គណនា Subtotal ដោយស្វ័យប្រវត្តិ' => false, 'ជ្រើសរូបមន្ត Subtotal ច្រើនដោយខ្លួនឯង' => false, 'លុប Field ចេញពី PivotTable' => false], null],
                ['single', 'StdDev (Standard Deviation) គណនាគម្លាតស្តង់ដារនៃ…', ['គំរូទិន្នន័យ (Sample) ស្មើនឹង STDEV.S' => true, 'ទិន្នន័យសរុបទាំងអស់ (Population) ស្មើនឹង STDEV.P' => false, 'ផលបូកសរុប' => false, 'ចំនួនជួរដេក' => false], 'StdDevp ទើបជាគម្លាតស្តង់ដារនៃទិន្នន័យសរុបទាំងអស់ (STDEV.P)។'],
            ]);

            // 5. Group, filter and data
            $m = $course->modules()->create(['position' => 5, 'title' => 'Group, Filter និង Data', 'description' => 'ផ្គុំទិន្នន័យជាក្រុម Filter ដោយ Slicer និង Timeline ហើយធ្វើបច្ចុប្បន្នភាព ឬប្តូរប្រភពទិន្នន័យ។']);
            $lesson($m, 1, 'Group Selection, Ungroup និង Group Field', 241, 258);
            $lesson($m, 2, 'Slicer, Timeline និង Filter Connections', 258, 275);
            $lesson($m, 3, 'Refresh, Change Data Source, Clear និង Move PivotTable', 275, 293);
            $this->quiz($course, $m, 4, 'quiz', 'តេស្ត Module 5៖ Group, Filter និង Data', [
                ['single', 'Shortcut សម្រាប់ Refresh តែ PivotTable ដែលកំពុងចុចលើ?', ['Alt + F5' => true, 'Ctrl + Alt + F5' => false, 'F11' => false, 'Alt + F1' => false], null],
                ['single', 'Refresh All (Ctrl + Alt + F5) ធ្វើបច្ចុប្បន្នភាព…', ['PivotTable, PivotChart និង Query ទាំងអស់ក្នុង File' => true, 'តែ PivotTable មួយដែលកំពុងជ្រើស' => false, 'តែ Chart ធម្មតា' => false, 'តែ Sheet ដែលកំពុងបើក' => false], null],
                ['single', 'Insert Timeline ប្រើសម្រាប់ Filter ទិន្នន័យប្រភេទណា?', ['កាលបរិច្ឆេទ (Date)' => true, 'អត្ថបទ' => false, 'លេខទូទៅ' => false, 'រូបភាព' => false], 'Timeline លេចឡើងតែពេលទិន្នន័យដើមមានជួរឈរ Date Format ត្រឹមត្រូវ។'],
                ['single', 'ចង់ឱ្យ Slicer មួយបញ្ជា PivotTable ច្រើនក្នុងពេលតែមួយ គួរប្រើ…', ['Filter Connections' => true, 'Change Data Source' => false, 'Move PivotTable' => false, 'Clear All' => false], null],
                ['single', 'ពេលទិន្នន័យដើមមានជួរដេកថ្មីបន្ថែម ហួសពី Range ចាស់ គួរប្រើ…', ['Change Data Source' => true, 'Clear Filters' => false, 'Group Selection' => false, 'Insert Timeline' => false], null],
                ['true_false', 'Group Field អាចផ្គុំកាលបរិច្ឆេទជា Years, Quarters, Months ឬ Days ដោយស្វ័យប្រវត្តិ។', true, null],
                ['single', 'ភាពខុសគ្នារវាង Clear All និង Clear Filters គឺ…', ['Clear All លុប Field ទាំងអស់ចេញ ចំណែក Clear Filters លុបតែ Filter' => true, 'Clear All លុបតែ Filter ចំណែក Clear Filters លុប Field ទាំងអស់' => false, 'ទាំងពីរលុប Sheet ចោល' => false, 'ទាំងពីរធ្វើដូចគ្នា' => false], null],
            ]);

            // 6. PivotChart
            $m = $course->modules()->create(['position' => 6, 'title' => 'PivotChart', 'description' => 'បង្ហាញលទ្ធផល PivotTable ជាក្រាហ្វិក ជ្រើសរើសប្រភេទ Chart ឱ្យត្រូវ និងបង្កើត Mini Dashboard។']);
            $lesson($m, 1, 'PivotChart ជាអ្វី និងរបៀបបង្កើត', 294, 312);
            $lesson($m, 2, 'ធាតុផ្សំ Tabs និងការជ្រើសរើសប្រភេទ Chart', 312, 330);
            $lesson($m, 3, 'ឧទាហរណ៍អនុវត្ត៖ ពី PivotChart ដល់ Mini Dashboard', 330, 377, ['practice' => true]);
            $lesson($m, 4, 'លំហាត់អនុវត្ត', 377, count($blocks), [
                'outro' => $this->exercisesTable(),
                'practice' => true,
            ]);
            $this->quiz($course, $m, 5, 'quiz', 'តេស្ត Module 6៖ PivotChart', [
                ['single', 'ផ្លូវកាត់ណាដែលបង្កើត PivotChart (ប្រភេទ Default) នៅក្នុង Sheet ដដែល?', ['Alt + F1' => true, 'F11' => false, 'Alt + F5' => false, 'Ctrl + T' => false], 'F11 បង្កើតវានៅក្នុង Chart Sheet ថ្មីដាច់ដោយឡែក។'],
                ['single', 'នៅលើ PivotChart តំបន់ Columns របស់ PivotTable ក្លាយជា…', ['Legend (Series)' => true, 'Axis (Categories)' => false, 'Values' => false, 'Filters' => false], null],
                ['single', 'នៅលើ PivotChart តំបន់ Rows របស់ PivotTable ក្លាយជា…', ['Axis (Categories)' => true, 'Legend (Series)' => false, 'Values' => false, 'Filters' => false], null],
                ['single', 'Chart ប្រភេទណាសមស្របបំផុត ដើម្បីបង្ហាញចំណែកភាគរយ Sale Local ធៀបនឹង Sale Export?', ['Pie / Doughnut' => true, 'Line' => false, 'Combo' => false, 'Stacked Column' => false], null],
                ['true_false', 'ប្រសិនបើលុប PivotTable ចោល PivotChart នឹងក្លាយជា Chart ធម្មតា ដែលលែង Update តទៅទៀត។', true, null],
                ['single', 'ចង់បង្ហាញតម្លៃ ២ ប្រភេទដែលមានខ្នាតខុសគ្នា (Amount និង Quantity) គួរប្រើ Chart ប្រភេទ…', ['Combo (ប្រើ Secondary Axis)' => true, 'Pie' => false, 'Clustered Bar' => false, 'Doughnut' => false], null],
                ['single', 'យោងតាមឧទាហរណ៍ ១ (មើលគ្រប់ឆ្នាំ) ខែណាលក់បានខ្ពស់ជាងគេ?', ['ខែ ១' => true, 'ខែ ២' => false, 'ខែ ៨' => false, 'ខែ ១២' => false], 'ខែ ១ លក់បាន $353,582.83 បន្ទាប់មកខែ ៨ ($344,513.16)។'],
            ]);

            // 7. Final assessment
            $m = $course->modules()->create(['position' => 7, 'title' => 'ការប្រឡងបញ្ចប់វគ្គ', 'description' => 'សាកល្បងជាមួយ Mock Exam កំណត់ម៉ោង រួចប្រឡង Final Exam ដើម្បីទទួលបានវិញ្ញាបនបត្រ។']);
            $this->quiz($course, $m, 1, 'mock', 'Mock Exam (ប្រឡងសាកល្បង)', [
                ['single', 'តំបន់ទាំង ៤ នៃផ្ទាំង PivotTable Fields គឺ…', ['Filters, Columns, Rows, Values' => true, 'Rows, Values, Charts, Slicers' => false, 'Header, Footer, Body, Total' => false, 'Insert, Design, Analyze, Format' => false], null],
                ['single', 'Tab ណាដែលមាន Subtotals, Grand Totals និង Report Layout?', ['PivotTable Design' => true, 'PivotTable Analyze' => false, 'Insert' => false, 'Data' => false], null],
                ['single', 'Show in Outline Form គឺជា…', ['ទម្រង់គ្រោង ដែលបំបែកជួរឈរតាម Field នីមួយៗ' => true, 'ទម្រង់ដើមដែលបង្រួមតូច' => false, 'ទម្រង់ដែលគ្មានផលបូក' => false, 'ទម្រង់ Chart' => false], null],
                ['single', 'For error values show ប្រើសម្រាប់…', ['ជំនួសតម្លៃ Error ដូចជា #N/A ឬ #DIV/0! ដោយអក្សរ ឬលេខដែលយើងកំណត់' => true, 'លាក់ជួរដេកដែលមាន Error' => false, 'លុប Field ដែលមាន Error' => false, 'ពណ៌ក្រហមលើក្រឡា Error' => false], null],
                ['single', '% of Row Total បង្ហាញភាគរយ ធៀបនឹង…', ['ផលបូកសរុបតាមជួរដេកនីមួយៗ' => true, 'ផលបូកសរុបតាមជួរឈរនីមួយៗ' => false, 'ផលបូករួមទាំងអស់' => false, 'ក្រុមធំតាមជួរឈរ' => false], null],
                ['single', 'Ungroup ប្រើសម្រាប់…', ['លុបការផ្គុំក្រុមដែលបានបង្កើតចោល' => true, 'ផ្គុំទិន្នន័យជាក្រុមថ្មី' => false, 'លុប PivotTable' => false, 'ប្តូរប្រភពទិន្នន័យ' => false], null],
                ['single', 'Move PivotTable ប្រើសម្រាប់…', ['រើ PivotTable ទាំងមូលទៅ New Worksheet ឬ Existing Worksheet' => true, 'ប្តូរឈ្មោះ PivotTable' => false, 'ចម្លង PivotTable ទៅ File ថ្មី' => false, 'លុប Field ទាំងអស់' => false], null],
                ['true_false', 'PivotChart អាច Filter ទិន្នន័យដោយផ្ទាល់នៅលើ Chart តាម Field Buttons, Slicer ឬ Timeline។', true, null],
                ['multiple', 'Tabs ណាខ្លះដែលលេចឡើងពេលចុចលើ PivotChart?', ['PivotChart Analyze' => true, 'Design' => true, 'Format' => true, 'Page Layout' => false], null],
                ['single', 'ចង់បង្ហាញនិន្នាការ (Trend) នៃការលក់តាមពេលវេលា គួរប្រើ Chart ប្រភេទ…', ['Line' => true, 'Pie' => false, 'Doughnut' => false, 'Clustered Bar' => false], null],
            ], ['time_limit' => 20, 'shuffle_questions' => true, 'description' => 'ប្រឡងសាកល្បងកំណត់ម៉ោង ២០ នាទី។ ធ្វើបានច្រើនដង ដើម្បីត្រៀមខ្លួនសម្រាប់ Final Exam។']);
            $this->quiz($course, $m, 2, 'final', 'Final Exam (ប្រឡងបញ្ចប់វគ្គ)', [
                ['single', 'PivotTable ជួយសង្ខេបទិន្នន័យ ដោយមិនចាំបាច់សរសេររូបមន្តស្មុគស្មាញដូចជា…', ['SUMIFS ឬ COUNTIFS' => true, 'TODAY ឬ NOW' => false, 'LEFT ឬ RIGHT' => false, 'UPPER ឬ LOWER' => false], null],
                ['single', 'Raw Data ដ៏ល្អសម្រាប់ PivotTable គួរ…', ['ជាទម្រង់ Table មាន Header ច្បាស់ និងគ្មានជួរទទេរនៅកណ្តាល' => true, 'មាន Merge Cells ច្រើន' => false, 'មានជួរទទេររវាងខែនីមួយៗ' => false, 'គ្មាន Header' => false], null],
                ['single', 'Field ណាដែលសមស្របដាក់ក្នុងតំបន់ Filters?', ['Field ដែលចង់ប្រើ Filter មើលទិន្នន័យទាំងមូល ដូចជា ឆ្នាំ ឬប្រទេស' => true, 'ទឹកប្រាក់លក់ដែលត្រូវបូក' => false, 'ឈ្មោះបុគ្គលិកដែលចង់រៀបរាប់តាមជួរដេក' => false, 'គ្មាន Field ណាអាចដាក់បាន' => false], null],
                ['single', 'Grand Totals → On for Columns Only មានន័យថា…', ['បើកផលបូកសរុបតែជួរឈរប៉ុណ្ណោះ' => true, 'បើកផលបូកសរុបតែជួរដេកប៉ុណ្ណោះ' => false, 'បិទផលបូកសរុបទាំងអស់' => false, 'បើកផលបូកសរុបទាំងជួរដេក និងជួរឈរ' => false], null],
                ['single', 'ពេលដាក់ Chart ឬ Slicer នៅក្បែរ PivotTable គួរធ្វើយ៉ាងណាជាមួយ Autofit column widths on update?', ['ដកគ្រីសចេញ ដើម្បីកុំឱ្យ Column ពង្រីក ឬបង្រួមដោយខ្លួនឯងពេល Filter' => true, 'គ្រីសបើកជានិច្ច' => false, 'លុប PivotTable ចោល' => false, 'គ្មានឥទ្ធិពលអ្វីទេ' => false], null],
                ['single', 'Save source data with file (គ្រីស) មានគុណ និងទោសអ្វី?', ['Filter បានលឿនពេលបើក File ប៉ុន្តែទំហំ File ធំជាងមុន' => true, 'File តូចជាងមុន ប៉ុន្តែត្រូវ Refresh មុននឹង Filter' => false, 'លុប Pivot Cache ចោល' => false, 'បិទមុខងារ Double-click' => false], null],
                ['single', 'Group Selection ប្រើសម្រាប់…', ['ផ្គុំឈ្មោះ ឬលេខដែលបាន Select ដោយដៃ ទៅជាក្រុមតែមួយ' => true, 'ផ្គុំកាលបរិច្ឆេទជាខែដោយស្វ័យប្រវត្តិ' => false, 'លុបក្រុមចោល' => false, 'បង្កើត Slicer' => false], 'ឧទាហរណ៍៖ ផ្គុំ ភ្នំពេញ + កណ្តាល ជា តំបន់កណ្តាល។'],
                ['single', 'Insert Slicer បង្កើត…', ['ប្រអប់ប៊ូតុងចុចសម្រាប់ Filter ទិន្នន័យ' => true, 'Chart ថ្មី' => false, 'Sheet ថ្មី' => false, 'រូបមន្តថ្មី' => false], null],
                ['single', 'ក្នុងឧទាហរណ៍ ៤ ដើម្បីបង្ហាញទំនិញលក់ដាច់ជាងគេ ១០ មុខ យើងប្រើ…', ['Value Filters → Top 10' => true, 'Label Filters → Begins With' => false, 'Sort A to Z' => false, 'Group Selection' => false], null],
                ['single', 'Chart ប្រភេទ Stacked Column ល្អសម្រាប់…', ['បង្ហាញទាំងផលបូកសរុប និងចំណែកនៃប្រភេទរងនីមួយៗ' => true, 'បង្ហាញចំណែកភាគរយតែប៉ុណ្ណោះ' => false, 'បង្ហាញនិន្នាការតាមពេលវេលាតែប៉ុណ្ណោះ' => false, 'បង្ហាញតម្លៃ ២ ខ្នាតខុសគ្នា' => false], null],
                ['true_false', 'ការ Filter ឬប្តូរ Field នៅលើ PivotChart នឹងប្តូរ PivotTable ដែលភ្ជាប់ជាមួយវាដែរ។', true, 'បើចង់បានទម្រង់មើលពីរផ្សេងគ្នា ត្រូវបង្កើត PivotTable ពីរ។'],
                ['multiple', 'ក្រោយទិន្នន័យដើមប្រែប្រួល វិធីណាខ្លះធ្វើឱ្យ PivotTable/PivotChart បង្ហាញលេខថ្មី?', ['Refresh (Alt + F5)' => true, 'Refresh All (Ctrl + Alt + F5)' => true, 'គ្រីស Refresh data when opening the file រួចបើក File ម្តងទៀត' => true, 'Clear All' => false], null],
            ], ['time_limit' => 30, 'max_attempts' => 3, 'pass_percent' => 75, 'shuffle_questions' => true, 'description' => 'ប្រឡងបញ្ចប់វគ្គ ៣០ នាទី មានឱកាស ៣ ដង។ ត្រូវបានពិន្ទុយ៉ាងតិច ៧៥% ដើម្បីទទួលបានវិញ្ញាបនបត្រ។']);
        });

        $this->command?->info('Pivot Table course created: '.count(array_filter($this->saved)).' screenshots, 19 lessons, 8 tests.');
    }

    private function course(DocxReader $doc): Course
    {
        $cover = $this->saveImage($doc, 'word/media/image86.jpeg', 'cover');

        $course = Course::create([
            'title' => 'Pivot Table និង PivotChart',
            'slug' => self::SLUG,
            'icon' => Course::whereNotNull('icon')->value('icon'),
            'image' => $cover ? ltrim(Str::after($cover, '/storage/'), '/') : null,
            'summary' => 'រៀនប្រើ PivotTable និង PivotChart ក្នុង Microsoft Excel ពីមូលដ្ឋានរហូតដល់ Mini Dashboard ជាមួយទិន្នន័យលក់ពិតជាង ៣៧,០០០ ជួរ។ សង្ខេប វិភាគ និងធ្វើរបាយការណ៍បានក្នុងរយៈពេលប៉ុន្មាននាទី ដោយមិនចាំបាច់សរសេររូបមន្តស្មុគស្មាញ។',
            'body' => '<p><strong>PivotTable</strong> គឺជាឧបករណ៍មួយដែលមានប្រយោជន៍បំផុតក្នុង Microsoft Excel សម្រាប់អ្នកដែលធ្វើការជាមួយទិន្នន័យច្រើន។ ក្នុងវគ្គសិក្សានេះ អ្នកនឹងរៀនពីការបង្កើត PivotTable ដំបូង រហូតដល់ការកំណត់ស៊ីជម្រៅ និងការបង្កើត <strong>PivotChart</strong> និង Mini Dashboard ដែលប្តូរព្រមគ្នាដោយចុចតែម្តង។</p>'
                .'<h3>វគ្គសិក្សានេះសម្រាប់អ្នកណា?</h3><ul><li>បុគ្គលិកការិយាល័យ គណនេយ្យ លក់ និងរដ្ឋបាល ដែលត្រូវធ្វើរបាយការណ៍ពីទិន្នន័យច្រើន</li><li>អ្នកដែលចេះប្រើ Excel កម្រិតមូលដ្ឋានរួចហើយ ហើយចង់វិភាគទិន្នន័យបានលឿនជាងមុន</li></ul>'
                .'<h3>របៀបរៀន</h3><p>មេរៀននីមួយៗពន្យល់ជាភាសាខ្មែរ ជាមួយរូបភាពអេក្រង់ Excel 365 ជាជំហានៗ។ អ្នកអនុវត្តលើ File Excel ដែលមានទិន្នន័យលក់ពិតឆ្នាំ 2019–2023 ធ្វើតេស្តក្រោយ Module នីមួយៗ ហើយប្រឡង Final Exam ដើម្បីទទួលបានវិញ្ញាបនបត្រ។</p>',
            'features' => [
                '✅ អ្វីដែលអ្នកនឹងរៀន៖',
                '- រៀបចំទិន្នន័យដើម និងបង្កើត PivotTable',
                '- Tab PivotTable Design និង PivotTable Options',
                '- Value Field Settings និង Show Values As (%)',
                '- Group, Slicer, Timeline និង Filter Connections',
                '- PivotChart និង Mini Dashboard',
                '✅ អ្វីដែលអ្នកទទួលបាន៖',
                '- មេរៀន ១៩ ជាមួយរូបភាពអេក្រង់ជាង ៩០',
                '- File អនុវត្ត Excel ទិន្នន័យលក់ពិតជាង ៣៧,០០០ ជួរ',
                '- លំហាត់ ១៥ និងតេស្តក្រោយ Module នីមួយៗ',
                '- Mock Exam, Final Exam និងវិញ្ញាបនបត្រ',
            ],
            'duration' => '8 hours',
            'currency' => 'USD',
            'certificate_enabled' => true,
            'is_free' => false,
            'is_published' => true,
            'sort_order' => (int) Course::where('slug', '!=', 'excel-quick-start')->max('sort_order') + 1,
        ]);

        $course->plans()->createMany([
            ['days' => 90, 'price' => 19, 'position' => 1],
            ['days' => 365, 'price' => 29, 'position' => 2],
            ['days' => null, 'price' => 45, 'position' => 3],
        ]);

        return $course;
    }

    /** One lesson from a run of the manual's blocks. */
    private function lesson(Course $course, CourseModule $module, int $position, string $title, array $blocks, array $extra = []): void
    {
        $html = DocxReader::html($blocks, fn ($url) => $url, fn ($block) => mb_strlen($block['text']) <= 60
            && ! preg_match('/[:៖]/u', $block['text'])
            && preg_match('/^(Tap |Tab |ផ្នែក|ប៊ូតុង |Value Field Settings$)/u', $block['text']));

        // The manual writes "Tap" for the dialog tabs (Tab Display, Tab Printing…).
        $html = preg_replace('/\bTap (?=(Totals?|Display|Printing|Data|Layout)\b)/u', 'Tab ', $html);
        $html = str_replace('Tab Total &amp;', 'Tab Totals &amp;', $html);
        $html = ($extra['intro'] ?? '').DocxReader::polish($html).($extra['outro'] ?? '');

        $images = array_sum(array_map(fn ($b) => count($b['images']), $blocks));
        $minutes = max(5, (int) ceil(mb_strlen(strip_tags($html)) / 450 + $images * 0.4));

        $lesson = $course->lessons()->create([
            'course_module_id' => $module->id,
            'title' => $title,
            'slug' => Slug::make($title),
            'position' => $position,
            'duration' => $minutes.':00',
            'body' => $html,
            'is_preview' => $extra['is_preview'] ?? false,
            'is_published' => true,
        ]);

        if (! empty($extra['practice'])) {
            $path = 'lessons/'.$course->id.'/pivottable-365-practice-'.Str::lower(Str::random(6)).'.xlsx';
            Storage::disk('local')->put($path, file_get_contents(base_path(self::WORKBOOK)));
            $lesson->update(['attachment_path' => $path, 'attachment_name' => self::WORKBOOK_NAME]);
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: array<string, bool>|bool, 3: ?string}>  $questions
     */
    private function quiz(Course $course, CourseModule $module, int $position, string $type, string $title, array $questions, array $settings = []): void
    {
        $quiz = $course->quizzes()->create(array_merge([
            'course_module_id' => $module->id,
            'type' => $type,
            'title' => $title,
            'slug' => Slug::make($title),
            'description' => 'ត្រូវឆ្លើយត្រូវយ៉ាងតិច ៧០% ដើម្បីបន្តទៅ Module បន្ទាប់។',
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
                ? [__('account.true', [], 'km') => $answers === true, __('account.false', [], 'km') => $answers === false]
                : $answers;
            $n = 0;
            foreach ($options as $text => $correct) {
                $question->options()->create(['text' => $text, 'is_correct' => $correct, 'position' => ++$n]);
            }
        }
    }

    /** Saves a screenshot from the manual (at most 1400 px wide) and returns its URL; null if it is not a web image. */
    private function saveImage(DocxReader $doc, string $path, string $name = ''): ?string
    {
        if (array_key_exists($path.$name, $this->saved)) {
            return $this->saved[$path.$name];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $bytes = $doc->image($path);
        if (! in_array($ext, ['jpg', 'png', 'gif', 'webp'], true) || $bytes === '' || ! ($img = @imagecreatefromstring($bytes))) {
            return $this->saved[$path.$name] = null;
        }

        if (imagesx($img) > 1400 || ($ext === 'jpg' && strlen($bytes) > 200_000)) {
            if (imagesx($img) > 1400) {
                $img = imagescale($img, 1400, -1, IMG_BICUBIC);
            }
            ob_start();
            if ($ext === 'png') {
                imagesavealpha($img, true);
                imagepng($img, null, 9);
            } else {
                imagejpeg($img, null, 84);
            }
            $bytes = ob_get_clean();
        }

        $stored = self::IMAGE_FOLDER.'/'.($name ? $name.'-' : '').Str::lower(Str::random(20)).'.'.$ext;
        Storage::disk('public')->put($stored, $bytes);

        return $this->saved[$path.$name] = '/storage/'.$stored;
    }

    /** The 15 exercises from the workbook's "Exercises" sheet, as a table. */
    private function exercisesTable(): string
    {
        $rows = $this->sheetRows(base_path(self::WORKBOOK), 'Exercises');
        array_shift($rows); // header row

        $body = '';
        foreach ($rows as $row) {
            [$number, $task, $setup] = array_pad($row, 3, '');
            if (str_contains($setup, 'Location Code')) {
                $setup .= ' (ចំណាំ៖ ទិន្នន័យគំរូមិនមានជួរឈរ Location Code ទេ។ សូមប្រើ Bill-to City ជំនួស)';
            }
            $body .= '<tr><td>'.e($number).'</td><td><strong>'.e($task).'</strong></td><td>'.e(str_replace(' | ', ' · ', $setup)).'</td></tr>';
        }

        return '<h3>លំហាត់ទាំង ១៥ ក្នុង File អនុវត្ត (Sheet Exercises)</h3>'
            .'<p>បើក File <strong>'.self::WORKBOOK_NAME.'</strong> បង្កើត PivotTable ថ្មីពី Sheet <strong>Pivot Table</strong> សម្រាប់លំហាត់នីមួយៗ ហើយប្រៀបធៀបលទ្ធផលជាមួយ Sheet <strong>PivotChart Example</strong>។</p>'
            .'<div class="table-scroll"><table><thead><tr><th>#</th><th>លំហាត់</th><th>ការរៀបចំ PivotTable / គោលដៅ</th></tr></thead><tbody>'.$body.'</tbody></table></div>';
    }

    /** @return list<list<string>> */
    private function sheetRows(string $file, string $sheetName): array
    {
        $zip = new ZipArchive;
        $zip->open($file);
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

        $target = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $id) {
                        $target = 'xl/'.ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
                    }
                }
            }
        }
        if (! $target) {
            return [];
        }

        $strings = [];
        foreach (simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'))->si as $si) {
            $text = (string) $si->t;
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        $rows = [];
        foreach (simplexml_load_string($zip->getFromName($target))->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                $cells[] = match ((string) $cell['t']) {
                    's' => $strings[(int) $value] ?? '',
                    'inlineStr' => (string) $cell->is->t,
                    default => $value,
                };
            }
            $rows[] = $cells;
        }

        return $rows;
    }
}
