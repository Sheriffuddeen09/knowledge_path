<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobSkill;
use Illuminate\Support\Str;

class JobSkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [

            'Software Development',
 'Website Development',
 'WordPress',
 'Laravel',
 'React.js',
 'Vue.js',
 'Angular',
 'Node.js',
 'PHP',
 'Python',
 'Java',
 'C#',
 'Flutter',
 'React Native',
 'Android Development',
 'iOS Development',
 'UI/UX Design',
 'Graphics Design',
 'Cyber Security',
 'Cloud Computing',
 'AI & Machine Learning',
 'Data Science',
 'DevOps',
 'QA Testing',
 // Business
 'Accounting',
 'Finance',
 'Insurance',
 'Human Resources',
 'Customer Support',
 'Sales',
 'Marketing',
 'Digital Marketing',
 'Business Development',
 // Writing
 'Writing',
 'Copywriting',
 'Technical Writing',
 'Content Writing',
 'Translation',
 'Proofreading',
 // Media
 'Video Editing',
 'Animation',
 // Administration
 'Virtual Assistant',
 'Data Entry',
 'Project Management',
 'Office Administration',
 // Education
 'Teaching',
 'Tutoring',
 'Research',
 // Health
 'Healthcare',
 'Nursing',
 'Pharmacy',
 'Medical Laboratory',
 // Engineering
 'Engineering',
 'Civil Engineering',
 'Mechanical Engineering',
 'Electrical Engineering',
 'Chemical Engineering',
 // Agriculture
 'Agriculture',
 'Livestock',
 'Fishery',
 'Forestry',
 // Construction
 'Construction',
 'Architecture',
 'Interior Design',
 // Transport
 'Driver',
 'Logistics',
 'Supply Chain',
 // Hospitality
 'Restaurant',
 'Chef',
 'Catering',
 'Tourism',
 // Others
 'Legal',
 'Security',
 'Fashion Design',
 'Real Estate',
 'Cleaning Services',
 'Manufacturing',
 'Printing',
 'Others'

        ];

        foreach ($skills as $skill) {

            JobSkill::updateOrCreate(
                [
                    'slug' => Str::slug($skill)
                ],
                [
                    'name' => $skill,
                    'slug' => Str::slug($skill),
                    'is_active' => true
                ]
            );

        }
    }
}