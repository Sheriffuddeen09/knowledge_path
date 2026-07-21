<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\JobCategory;
class JobCategorySeeder extends Seeder
{
 public function run(): void
 {
 $categories = [
 // Technology
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
 'Banking',
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
 'Photography',
 'Videography',
 'Video Editing',
 'Animation',
 'Music Production',
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
 'Hotel Management',
 'Restaurant',
 'Chef',
 'Catering',
 'Tourism',
 // Others
 'Legal',
 'Security',
 'Fashion Design',
 'Beauty & Cosmetics',
 'Real Estate',
 'Cleaning Services',
 'Manufacturing',
 'Printing',
 'Others'
 ];
 foreach ($categories as $category) {
 JobCategory::firstOrCreate([
 'name' => $category
 ]);
 }
 }
}