<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo school: 1 admin, 6 teachers, 8 subjects, 6 classes, 42 students with a parent contact each,
 * and a weekly timetable. Only the admin and the teachers can log in.
 * Every demo account uses the password "password".
 */
class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Accounts ----------
        User::create([
            'name' => 'Sok Admin', 'name_km' => 'សុខ អេតមីន',
            'email' => 'admin@school.edu.kh', 'phone' => '012000111',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);

        // [Latin name, Khmer name, email, phone]
        $teacherRows = [
            'MAT' => ['Pen Sovann', 'ប៉ែន សុវណ្ណ', 'sovann@school.edu.kh', '012345678'],
            'KHM' => ['Mao Sreyleak', 'ម៉ៅ ស្រីល័ក្ខ', 'sreyleak@school.edu.kh', '011222333'],
            'PHY' => ['Ros Piseth', 'រស់ ពិសិដ្ឋ', 'piseth@school.edu.kh', '015678901'],
            'CHE' => ['Tep Mony', 'ទេព មុនី', 'mony@school.edu.kh', '017890123'],
            'ENG' => ['Hak Chanthou', 'ហាក់ ចាន់ធូ', 'chanthou@school.edu.kh', '010456789'],
            'HIS' => ['Long Davin', 'ឡុង ដាវីន', 'davin@school.edu.kh', '016234567'],
        ];
        $teachers = [];
        foreach ($teacherRows as $code => [$name, $nameKm, $email, $phone]) {
            $teachers[$code] = User::create([
                'name' => $name, 'name_km' => $nameKm, 'email' => $email, 'phone' => $phone,
                'password' => 'password', 'role' => User::ROLE_TEACHER,
            ]);
        }
        // Some teachers teach two subjects.
        $teachers['BIO'] = $teachers['CHE'];
        $teachers['ICT'] = $teachers['HIS'];

        // ---------- Academic year ----------
        $year = AcademicYear::create([
            'name' => '2025-2026', 'start_date' => '2025-11-03', 'end_date' => '2026-08-28', 'is_current' => true,
        ]);

        // ---------- Subjects ----------
        $subjectRows = [
            ['KHM', 'Khmer Literature', 'អក្សរសាស្ត្រខ្មែរ', 6],
            ['MAT', 'Mathematics', 'គណិតវិទ្យា', 6],
            ['PHY', 'Physics', 'រូបវិទ្យា', 4],
            ['CHE', 'Chemistry', 'គីមីវិទ្យា', 4],
            ['BIO', 'Biology', 'ជីវវិទ្យា', 3],
            ['HIS', 'History', 'ប្រវត្តិវិទ្យា', 2],
            ['ENG', 'English', 'ភាសាអង់គ្លេស', 4],
            ['ICT', 'Computer (ICT)', 'កុំព្យូទ័រ', 2],
        ];
        $subjects = [];
        foreach ($subjectRows as [$code, $nameEn, $nameKm, $hours]) {
            $subjects[$code] = Subject::create([
                'code' => $code, 'name_en' => $nameEn, 'name_km' => $nameKm, 'hours_per_week' => $hours,
            ]);
        }

        // ---------- Classes, with a homeroom teacher each ----------
        $classRows = [
            ['10A', 10, '101', 'MAT'], ['10B', 10, '102', 'KHM'],
            ['11A', 11, '201', 'PHY'], ['11B', 11, '202', 'CHE'],
            ['12A', 12, '301', 'ENG'], ['12B', 12, '302', 'HIS'],
        ];
        $classes = [];
        foreach ($classRows as [$name, $grade, $room, $homeroom]) {
            $classes[$name] = SchoolClass::create([
                'academic_year_id' => $year->id, 'homeroom_teacher_id' => $teachers[$homeroom]->id,
                'name' => $name, 'grade' => $grade, 'room' => $room,
            ]);
        }

        // ---------- Teaching assignments: every subject in every class ----------
        foreach ($classes as $class) {
            foreach ($subjects as $code => $subject) {
                TeachingAssignment::create([
                    'teacher_id' => $teachers[$code]->id,
                    'school_class_id' => $class->id,
                    'subject_id' => $subject->id,
                ]);
            }
        }

        // ---------- Students: 7 per class ----------
        // [Latin name, Khmer name, gender]
        $names = [
            ['Sok Dara', 'សុខ ដារា', 'male'], ['Chan Sreyneang', 'ចាន់ ស្រីនាង', 'female'],
            ['Ouk Sreypov', 'អ៊ុក ស្រីពៅ', 'female'], ['Kim Vannak', 'គឹម វណ្ណៈ', 'male'],
            ['Heng Sophea', 'ហេង សុភា', 'female'], ['Ly Bopha', 'លី បុប្ផា', 'female'],
            ['Chea Rithy', 'ជា រិទ្ធី', 'male'], ['Noun Pisey', 'នួន ពិសី', 'female'],
            ['Meas Sokha', 'មាស សុខា', 'male'], ['Phan Chenda', 'ផាន់ ចិន្តា', 'female'],
            ['Touch Vuthy', 'ទូច វុទ្ធី', 'male'], ['Sim Kunthea', 'ស៊ឹម គន្ធា', 'female'],
            ['Yim Ratanak', 'យិម រតនៈ', 'male'], ['Keo Makara', 'កែវ មករា', 'male'],
            ['Sao Bunthoeun', 'សៅ ប៊ុនធឿន', 'male'], ['Prak Sokunthea', 'ប្រាក់ សុគន្ធា', 'female'],
            ['Nhem Vichea', 'ញ៉ែម វិជ្ជា', 'male'], ['Khiev Sreymom', 'ខៀវ ស្រីមុំ', 'female'],
            ['Seng Rattana', 'សេង រតនា', 'female'], ['Chhun Lina', 'ឈុន លីណា', 'female'],
            ['Duong Panha', 'ដួង បញ្ញា', 'male'], ['Em Sothea', 'អែម សុធា', 'male'],
            ['Hun Chanra', 'ហ៊ុន ចាន់រ៉ា', 'male'], ['In Sopheak', 'អ៊ិន សុភ័ក្ត្រ', 'female'],
            ['Kong Mealea', 'គង់ មាលា', 'female'], ['Lim Kimheng', 'លឹម គីមហេង', 'male'],
            ['Mam Sovannara', 'ម៉ម សុវណ្ណារ៉ា', 'male'], ['Nget Thida', 'ង៉ែត ធីតា', 'female'],
            ['Oeun Sambath', 'អឿន សម្បត្តិ', 'male'], ['Pich Kanha', 'ពេជ្រ កញ្ញា', 'female'],
            ['Run Veasna', 'រុន វាសនា', 'male'], ['San Devi', 'សាន ទេវី', 'female'],
            ['Thach Sokly', 'ថាច់ សុខលី', 'male'], ['Uy Molika', 'អ៊ុយ មល្លិកា', 'female'],
            ['Van Sereyvuth', 'វ៉ាន់ សិរីវុធ', 'male'], ['Yos Chhorvy', 'យស់ ឆវី', 'female'],
            ['Chheang Visal', 'ឈាង វិសាល', 'male'], ['Kang Sreyroth', 'កាង ស្រីរ័ត្ន', 'female'],
            ['Long Piseth', 'ឡុង ពិសិដ្ឋ', 'male'], ['Mok Socheata', 'មុក សុជាតា', 'female'],
            ['Nov Rotha', 'ណូវ រដ្ឋា', 'male'], ['Sar Leakena', 'សារ លក្ខិណា', 'female'],
        ];

        $index = 0;
        $students = [];
        foreach ($classes as $className => $class) {
            for ($n = 1; $n <= 7; $n++) {
                [$latin, $khmer, $gender] = $names[$index++];
                $students[$latin] = Student::create([
                    'school_class_id' => $class->id,
                    'student_code' => sprintf('S-%s-%03d', $className, $n),
                    'name_latin' => $latin,
                    'name_km' => $khmer,
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears($class->grade + 6)->subDays(($index * 37) % 365)->toDateString(),
                ]);
            }
        }

        // ---------- Parents: contacts only, one per student ----------
        // Most parents joined the school's Telegram bot; the rest get SMS.
        $parentNames = [
            'male' => [['Chantha', 'ចន្ថា'], ['Samnang', 'សំណាង'], ['Sophal', 'សុផល'], ['Sarath', 'សារ៉ាត់'], ['Bora', 'បូរ៉ា'], ['Kosal', 'កុសល']],
            'female' => [['Sokunthea', 'សុគន្ធា'], ['Vanna', 'វណ្ណា'], ['Sophy', 'សុភី'], ['Channy', 'ចាន់នី'], ['Dalin', 'ដាលីន'], ['Malis', 'ម្លិះ']],
        ];
        $i = 0;
        foreach ($students as $student) {
            $i++;
            $isFather = $i % 3 !== 0;
            [$given, $givenKm] = $parentNames[$isFather ? 'male' : 'female'][$i % 6];
            $family = strtok($student->name_latin, ' ');
            $familyKm = strtok($student->name_km, ' ');

            $guardian = Guardian::create([
                'name' => "$family $given",
                'name_km' => "$familyKm $givenKm",
                'phone' => sprintf('09%07d', 1000000 + $i * 7919),
                // Demo chat ids; a real id comes from the Telegram bot when the parent presses Start
                'telegram_chat_id' => $i % 7 === 0 ? null : (string) (700000000 + $i),
            ]);
            $student->guardians()->attach($guardian->id, ['relation' => $isFather ? 'father' : 'mother']);
        }

        // ---------- Weekly timetable: Monday to Saturday, 5 periods a day ----------
        // Each subject gets about its hours per week, and no teacher is in two classes at once.
        $teacherBusy = [];
        foreach ($classes as $class) {
            $hoursLeft = collect($subjectRows)->mapWithKeys(fn ($row) => [$row[0] => $row[3]])->all();
            $turn = 0;
            for ($weekday = 1; $weekday <= 6; $weekday++) {
                foreach (TimetableSlot::PERIODS as $period => [$start, $end]) {
                    // Rotate through the subjects so each class has a different order
                    $codes = array_keys($hoursLeft);
                    $order = array_merge(array_slice($codes, ($turn + $class->id) % count($codes)), array_slice($codes, 0, ($turn + $class->id) % count($codes)));
                    $turn++;
                    $pick = collect($order)->first(fn ($code) => $hoursLeft[$code] > 0
                        && ! isset($teacherBusy[$teachers[$code]->id][$weekday][$period]));
                    if (! $pick) {
                        continue; // free period
                    }
                    $hoursLeft[$pick]--;
                    $teacherBusy[$teachers[$pick]->id][$weekday][$period] = true;

                    TimetableSlot::create([
                        'school_class_id' => $class->id,
                        'subject_id' => $subjects[$pick]->id,
                        'teacher_id' => $teachers[$pick]->id,
                        'weekday' => $weekday,
                        'period' => $period,
                        'start_time' => $start,
                        'end_time' => $end,
                    ]);
                }
            }
        }
    }
}
