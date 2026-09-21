<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\JobCategory;
use Illuminate\Support\Str;


class JobCategorySeeder extends Seeder
{
 public function run(): void
 {
 $categories = [
 // Technology
 'Software Development',
 'Website Development',
 'WordPress Developer',
 'Laravel Developer',
 'React.js Developer',
 'Vue.js Developer',
 'Angular Developer',
 'Node.js Developer',
 'PHP Developer',
 'Python Developer',
 'Java Developer',
 'C# Developer',
 'Flutter Developer',
 'React Native Developer',
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
 
 foreach ($categories as $index => $category) {

            JobCategory::updateOrCreate(
                ['slug' => Str::slug($category)],
                [
                    'name' => $category,
                    'slug' => Str::slug($category),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );

        }
    }

}