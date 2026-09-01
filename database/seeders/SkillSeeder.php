<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    /**
     * Canonical skill taxonomy (~200 entries) across the T&T industry mix.
     *
     * Format: 'Canonical Name' => [category, [aliases...]]
     * Aliases power alias-aware matching (JS ≈ JavaScript, MS Excel ≈ Microsoft Excel).
     * Matching must always compare against name + aliases, case-insensitively.
     */
    public function run(): void
    {
        $skills = [
            // ── ICT & Software ────────────────────────────────────────────
            'JavaScript'                 => ['software-it', ['JS', 'ECMAScript']],
            'TypeScript'                 => ['software-it', ['TS']],
            'PHP'                        => ['software-it', []],
            'Laravel'                    => ['software-it', ['Laravel Framework']],
            'Python'                     => ['software-it', []],
            'Java'                       => ['software-it', []],
            'C#'                         => ['software-it', ['C Sharp', 'CSharp']],
            '.NET'                       => ['software-it', ['dotnet', 'ASP.NET', '.NET Core']],
            'C++'                        => ['software-it', ['CPP']],
            'SQL'                        => ['software-it', ['Structured Query Language']],
            'MySQL'                      => ['software-it', []],
            'PostgreSQL'                 => ['software-it', ['Postgres']],
            'HTML'                       => ['software-it', ['HTML5']],
            'CSS'                        => ['software-it', ['CSS3']],
            'React'                      => ['software-it', ['React.js', 'ReactJS']],
            'Vue.js'                     => ['software-it', ['Vue', 'VueJS']],
            'Node.js'                    => ['software-it', ['Node', 'NodeJS']],
            'Git'                        => ['software-it', ['GitHub', 'GitLab', 'Version Control']],
            'REST APIs'                  => ['software-it', ['REST', 'RESTful APIs', 'API Development']],
            'Amazon Web Services'        => ['software-it', ['AWS']],
            'Microsoft Azure'            => ['software-it', ['Azure']],
            'Google Cloud Platform'      => ['software-it', ['GCP', 'Google Cloud']],
            'Docker'                     => ['software-it', ['Containers', 'Containerization']],
            'Linux'                      => ['software-it', ['Ubuntu', 'Linux Administration']],
            'Computer Networking'        => ['software-it', ['Networking', 'TCP/IP', 'LAN/WAN']],
            'Cybersecurity'              => ['software-it', ['Information Security', 'InfoSec', 'IT Security']],
            'IT Support'                 => ['software-it', ['Help Desk', 'Helpdesk', 'Desktop Support', 'Technical Support']],
            'Microsoft 365 Administration' => ['software-it', ['Office 365 Admin', 'M365']],
            'Active Directory'           => ['software-it', ['AD', 'Entra ID']],
            'Power BI'                   => ['software-it', ['Microsoft Power BI']],
            'Data Analysis'              => ['software-it', ['Data Analytics']],
            'Machine Learning'           => ['software-it', ['ML']],
            'WordPress'                  => ['software-it', ['WP']],
            'UI/UX Design'               => ['software-it', ['UX Design', 'UI Design', 'User Experience', 'Figma']],
            'Software Testing'           => ['software-it', ['QA', 'Quality Assurance', 'QA Testing']],
            'Agile Methodologies'        => ['software-it', ['Agile', 'Scrum', 'Kanban']],
            'DevOps'                     => ['software-it', ['CI/CD']],
            'Mobile App Development'     => ['software-it', ['Android Development', 'iOS Development', 'Flutter', 'React Native']],
            'Salesforce'                 => ['software-it', ['SFDC']],
            'SAP'                        => ['software-it', ['SAP ERP']],
            'Database Administration'    => ['software-it', ['DBA']],
            'Excel VBA'                  => ['software-it', ['VBA', 'Macros']],

            // ── Office & Administration ───────────────────────────────────
            'Microsoft Excel'            => ['office-admin', ['MS Excel', 'Excel', 'Spreadsheets']],
            'Microsoft Word'             => ['office-admin', ['MS Word', 'Word Processing']],
            'Microsoft PowerPoint'       => ['office-admin', ['PowerPoint', 'MS PowerPoint', 'Presentations']],
            'Microsoft Outlook'          => ['office-admin', ['Outlook', 'Email Management']],
            'Data Entry'                 => ['office-admin', ['Data Input', 'Keyboarding']],
            'Typing'                     => ['office-admin', ['Touch Typing', 'Typing Speed']],
            'Records Management'         => ['office-admin', ['Filing', 'Document Management']],
            'Minute Taking'              => ['office-admin', ['Meeting Minutes']],
            'Office Administration'      => ['office-admin', ['Administrative Support', 'Admin Assistant', 'Clerical Work']],
            'Reception'                  => ['office-admin', ['Front Desk', 'Receptionist Duties', 'Switchboard']],
            'Scheduling'                 => ['office-admin', ['Calendar Management', 'Appointment Setting']],
            'Report Writing'             => ['office-admin', ['Business Writing']],
            'Document Control'           => ['office-admin', []],
            'Inventory Management'       => ['office-admin', ['Stock Control', 'Stock Taking', 'Inventory Control']],

            // ── Finance & Accounting ──────────────────────────────────────
            'Bookkeeping'                => ['finance-accounting', ['Books of Accounts']],
            'Accounts Payable'           => ['finance-accounting', ['AP']],
            'Accounts Receivable'        => ['finance-accounting', ['AR']],
            'Payroll Processing'         => ['finance-accounting', ['Payroll']],
            'QuickBooks'                 => ['finance-accounting', ['QuickBooks Online', 'QBO']],
            'Sage Accounting'            => ['finance-accounting', ['Peachtree', 'Sage 50']],
            'Financial Reporting'        => ['finance-accounting', ['Financial Statements']],
            'Auditing'                   => ['finance-accounting', ['Internal Audit', 'External Audit']],
            'Taxation'                   => ['finance-accounting', ['Tax Preparation', 'VAT', 'PAYE']],
            'Budgeting'                  => ['finance-accounting', ['Budget Preparation', 'Forecasting']],
            'Financial Analysis'         => ['finance-accounting', ['Financial Modelling']],
            'IFRS'                       => ['finance-accounting', ['International Financial Reporting Standards']],
            'Credit Analysis'            => ['finance-accounting', ['Credit Assessment', 'Lending']],
            'Insurance Underwriting'     => ['finance-accounting', ['Underwriting']],
            'Claims Processing'          => ['finance-accounting', ['Insurance Claims']],
            'AML Compliance'             => ['finance-accounting', ['Anti-Money Laundering', 'KYC', 'Compliance']],
            'Risk Management'            => ['finance-accounting', ['Risk Assessment']],
            'Treasury Management'        => ['finance-accounting', ['Cash Management']],
            'Cash Handling'              => ['finance-accounting', ['Cashier', 'Teller Operations']],

            // ── Energy & Technical Trades ─────────────────────────────────
            'Process Plant Operations'   => ['trades-energy', ['Process Operations', 'Plant Operator']],
            'Instrumentation'            => ['trades-energy', ['Instrument Technician', 'Process Control']],
            'Industrial Electrical'      => ['trades-energy', ['Electrical Installation', 'Electrician', 'Electrical Maintenance']],
            'Welding'                    => ['trades-energy', ['MIG Welding', 'TIG Welding', 'Arc Welding', 'Fabrication']],
            'Pipefitting'                => ['trades-energy', ['Pipe Fitter']],
            'Millwright'                 => ['trades-energy', ['Industrial Mechanic']],
            'HVAC'                       => ['trades-energy', ['Air Conditioning', 'Refrigeration', 'AC Technician']],
            'Plumbing'                   => ['trades-energy', ['Pipework']],
            'Carpentry'                  => ['trades-energy', ['Joinery', 'Woodworking']],
            'Masonry'                    => ['trades-energy', ['Block Laying', 'Concrete Work']],
            'Heavy Equipment Operation'  => ['trades-energy', ['Excavator Operation', 'Backhoe Operation']],
            'Crane Operation'            => ['trades-energy', ['Crane Operator']],
            'Forklift Operation'         => ['trades-energy', ['Forklift Certified', 'Forklift Driver']],
            'Rigging'                    => ['trades-energy', ['Rigger', 'Lifting Operations']],
            'Scaffolding'                => ['trades-energy', ['Scaffold Erection']],
            'Occupational Health & Safety' => ['trades-energy', ['HSE', 'OSHA', 'Safety Officer', 'HSSE']],
            'Permit-to-Work Systems'     => ['trades-energy', ['PTW']],
            'P&ID Reading'               => ['trades-energy', ['Piping and Instrumentation Diagrams']],
            'Mechanical Maintenance'     => ['trades-energy', ['Preventive Maintenance', 'Machine Maintenance']],
            'Corrosion Control'          => ['trades-energy', ['Coatings Inspection']],
            'Non-Destructive Testing'    => ['trades-energy', ['NDT']],
            'Automotive Repair'          => ['trades-energy', ['Motor Vehicle Mechanic', 'Auto Mechanic']],

            // ── Healthcare ────────────────────────────────────────────────
            'Patient Care'               => ['healthcare', ['Bedside Care']],
            'Phlebotomy'                 => ['healthcare', ['Blood Collection']],
            'Pharmacy Assistance'        => ['healthcare', ['Pharmacy Technician', 'Dispensing']],
            'Medical Records'            => ['healthcare', ['Health Records', 'Health Information Management']],
            'Nursing Assistance'         => ['healthcare', ['Nursing Assistant', 'Patient Care Assistant', 'CNA']],
            'First Aid & CPR'            => ['healthcare', ['First Aid', 'CPR', 'Basic Life Support', 'BLS']],
            'Vital Signs Monitoring'     => ['healthcare', ['Vitals']],
            'Infection Control'          => ['healthcare', ['Sterilization']],
            'Medical Billing & Coding'   => ['healthcare', ['Medical Billing', 'Medical Coding']],
            'Caregiving'                 => ['healthcare', ['Elderly Care', 'Geriatric Care', 'Home Care']],
            'Laboratory Techniques'      => ['healthcare', ['Lab Assistant', 'Sample Processing']],
            'Medication Administration'  => ['healthcare', []],

            // ── Tourism & Hospitality ─────────────────────────────────────
            'Food & Beverage Service'    => ['hospitality-tourism', ['F&B', 'Waiting Tables', 'Waitstaff']],
            'Bartending'                 => ['hospitality-tourism', ['Mixology', 'Bar Service']],
            'Housekeeping'               => ['hospitality-tourism', ['Room Attendant', 'Janitorial']],
            'Hotel Front Desk Operations' => ['hospitality-tourism', ['Front Office', 'Guest Services']],
            'Food Safety'                => ['hospitality-tourism', ['HACCP', 'Food Handling', 'Food Badge']],
            'Culinary Arts'              => ['hospitality-tourism', ['Cooking', 'Chef Skills', 'Kitchen Operations']],
            'Baking & Pastry'            => ['hospitality-tourism', ['Baking', 'Pastry Arts']],
            'Event Planning'             => ['hospitality-tourism', ['Event Management', 'Event Coordination']],
            'Tour Guiding'               => ['hospitality-tourism', ['Tour Operations']],
            'Reservations Systems'       => ['hospitality-tourism', ['Booking Systems', 'Opera PMS']],
            'Barista Skills'             => ['hospitality-tourism', ['Barista', 'Coffee Preparation']],

            // ── Sales, Retail & Marketing ─────────────────────────────────
            'Sales'                      => ['sales-marketing', ['Selling', 'B2B Sales', 'Retail Sales']],
            'Merchandising'              => ['sales-marketing', ['Visual Merchandising']],
            'Point of Sale Systems'      => ['sales-marketing', ['POS', 'Cash Register']],
            'Customer Service'           => ['sales-marketing', ['Customer Support', 'Client Service', 'Customer Care']],
            'Digital Marketing'          => ['sales-marketing', ['Online Marketing', 'Internet Marketing']],
            'Social Media Management'    => ['sales-marketing', ['Social Media Marketing', 'Community Management']],
            'Search Engine Optimization' => ['sales-marketing', ['SEO']],
            'Content Writing'            => ['sales-marketing', ['Content Creation', 'Blogging']],
            'Copywriting'                => ['sales-marketing', ['Ad Copy']],
            'Email Marketing'            => ['sales-marketing', ['Mailchimp', 'Newsletters']],
            'Graphic Design'             => ['sales-marketing', ['Visual Design', 'Canva']],
            'Adobe Photoshop'            => ['sales-marketing', ['Photoshop']],
            'Adobe Illustrator'          => ['sales-marketing', ['Illustrator']],
            'Brand Management'           => ['sales-marketing', ['Branding']],
            'Market Research'            => ['sales-marketing', ['Consumer Research']],
            'Paid Advertising'           => ['sales-marketing', ['Google Ads', 'Facebook Ads', 'PPC', 'Meta Ads']],

            // ── BPO & Contact Centre ──────────────────────────────────────
            'Call Handling'              => ['bpo-contact-centre', ['Inbound Calls', 'Outbound Calls', 'Call Centre Experience']],
            'CRM Software'               => ['bpo-contact-centre', ['CRM', 'HubSpot CRM', 'Zoho CRM']],
            'Ticketing Systems'          => ['bpo-contact-centre', ['Zendesk', 'Freshdesk', 'ServiceNow', 'Jira Service Desk']],
            'Live Chat Support'          => ['bpo-contact-centre', ['Chat Support']],
            'Telemarketing'              => ['bpo-contact-centre', ['Telesales', 'Cold Calling']],
            'KPI Reporting'              => ['bpo-contact-centre', ['Performance Metrics', 'SLA Management']],
            'Data Processing'            => ['bpo-contact-centre', ['Transaction Processing']],

            // ── Logistics & Shipping ──────────────────────────────────────
            'Supply Chain Management'    => ['logistics-shipping', ['SCM', 'Supply Chain']],
            'Customs Brokerage'          => ['logistics-shipping', ['Customs Clearance', 'Customs Documentation']],
            'Freight Forwarding'         => ['logistics-shipping', ['Freight Operations']],
            'Warehouse Operations'       => ['logistics-shipping', ['Warehousing', 'Picking and Packing']],
            'Shipping Documentation'     => ['logistics-shipping', ['Bills of Lading', 'Export Documents']],
            'Fleet Management'           => ['logistics-shipping', ['Vehicle Fleet']],
            'Dispatching'                => ['logistics-shipping', ['Dispatch Operations']],
            'Import/Export Procedures'   => ['logistics-shipping', ['Import Export', 'Trade Compliance']],
            'Route Planning'             => ['logistics-shipping', ['Delivery Scheduling']],
            'Driving'                    => ['logistics-shipping', ['Delivery Driver', 'Chauffeur', 'Van Driver']],

            // ── Construction & Built Environment ──────────────────────────
            'Project Management'         => ['construction', ['PM', 'Project Coordination', 'PMP']],
            'AutoCAD'                    => ['construction', ['CAD', 'Computer-Aided Design', 'Drafting']],
            'Quantity Surveying'         => ['construction', ['QS', 'Cost Estimation']],
            'Site Supervision'           => ['construction', ['Site Management', 'Foreman']],
            'Blueprint Reading'          => ['construction', ['Construction Drawings', 'Technical Drawings']],
            'Construction Estimating'    => ['construction', ['Estimating', 'Tendering']],
            'Land Surveying'             => ['construction', ['Surveying']],
            'Building Codes & Standards' => ['construction', ['Building Regulations']],

            // ── Agriculture & Agro-processing ─────────────────────────────
            'Crop Production'            => ['agriculture', ['Crop Farming', 'Horticulture']],
            'Livestock Management'       => ['agriculture', ['Animal Husbandry', 'Poultry Farming']],
            'Agro-processing'            => ['agriculture', ['Food Manufacturing', 'Agri-processing']],
            'Greenhouse Operations'      => ['agriculture', ['Protected Agriculture', 'Hydroponics']],
            'Irrigation Systems'         => ['agriculture', ['Irrigation']],
            'Pest Management'            => ['agriculture', ['Integrated Pest Management', 'IPM', 'Pesticide Application']],
            'Food Processing'            => ['agriculture', ['Food Production']],
            'Aquaculture'                => ['agriculture', ['Fish Farming']],

            // ── Education ─────────────────────────────────────────────────
            'Lesson Planning'            => ['education', ['Instructional Planning']],
            'Classroom Management'       => ['education', []],
            'Curriculum Development'     => ['education', ['Curriculum Design', 'Instructional Design']],
            'Early Childhood Education'  => ['education', ['ECCE', 'Preschool Teaching']],
            'Special Needs Education'    => ['education', ['Special Education', 'SEN']],
            'Tutoring'                   => ['education', ['Private Lessons', 'Academic Coaching']],

            // ── Creative & Media ──────────────────────────────────────────
            'Video Editing'              => ['creative-media', ['Adobe Premiere Pro', 'Final Cut Pro', 'DaVinci Resolve']],
            'Photography'                => ['creative-media', ['Photo Shooting']],
            'Videography'                => ['creative-media', ['Video Production', 'Camera Operation']],
            'Animation'                  => ['creative-media', ['2D Animation', '3D Animation']],
            'Illustration'               => ['creative-media', ['Digital Illustration']],
            'Journalism'                 => ['creative-media', ['News Writing', 'Reporting']],
            'Audio Production'           => ['creative-media', ['Sound Engineering', 'Music Production', 'Mixing']],
            '3D Modelling'               => ['creative-media', ['Blender', '3D Modeling']],
            'Motion Graphics'            => ['creative-media', ['After Effects']],

            // ── Professional Services & HR ────────────────────────────────
            'Legal Research'             => ['professional-services', []],
            'Paralegal Work'             => ['professional-services', ['Legal Assistant', 'Paralegal']],
            'Contract Drafting'          => ['professional-services', ['Contract Management']],
            'Human Resources'            => ['professional-services', ['HR', 'HR Administration', 'HRM']],
            'Recruitment'                => ['professional-services', ['Talent Acquisition', 'Hiring']],
            'Training & Development'     => ['professional-services', ['L&D', 'Corporate Training', 'Facilitation']],
            'Business Analysis'          => ['professional-services', ['BA', 'Requirements Gathering']],
            'Management Consulting'      => ['professional-services', ['Consulting']],
            'Public Relations'           => ['professional-services', ['PR', 'Corporate Communications']],
            'Procurement'                => ['professional-services', ['Purchasing', 'Sourcing', 'Vendor Management']],
            'Security Operations'        => ['professional-services', ['Security Guard', 'Estate Security', 'Loss Prevention']],

            // ── Soft skills ───────────────────────────────────────────────
            'Communication'              => ['soft-skills', ['Verbal Communication', 'Written Communication', 'Interpersonal Skills']],
            'Teamwork'                   => ['soft-skills', ['Collaboration', 'Team Player']],
            'Leadership'                 => ['soft-skills', ['Team Leadership', 'People Management', 'Supervision']],
            'Time Management'            => ['soft-skills', ['Prioritization', 'Organizational Skills']],
            'Problem Solving'            => ['soft-skills', ['Troubleshooting', 'Analytical Skills']],
            'Critical Thinking'          => ['soft-skills', []],
            'Adaptability'               => ['soft-skills', ['Flexibility']],
            'Negotiation'                => ['soft-skills', []],
            'Presentation Skills'        => ['soft-skills', ['Public Speaking']],
            'Conflict Resolution'        => ['soft-skills', ['Mediation']],
            'Attention to Detail'        => ['soft-skills', ['Accuracy', 'Detail-Oriented']],

            // ── Languages ─────────────────────────────────────────────────
            'Spanish'                    => ['languages', ['Spanish Language']],
            'French'                     => ['languages', ['French Language']],

            // ── Remote-work signals ───────────────────────────────────────
            'Remote Collaboration'       => ['remote-work', ['Remote Work Experience', 'Distributed Teams', 'Working From Home']],
            'Slack'                      => ['remote-work', ['Microsoft Teams', 'Team Chat Tools']],
            'Video Conferencing'         => ['remote-work', ['Zoom', 'Google Meet']],
            'Project Tracking Tools'     => ['remote-work', ['Asana', 'Trello', 'Jira', 'Monday.com', 'ClickUp']],
            'Documentation Tools'        => ['remote-work', ['Notion', 'Confluence']],
            'Asynchronous Communication' => ['remote-work', ['Async Communication']],
        ];

        $now = now();

        $rows = collect($skills)->map(fn (array $meta, string $name) => [
            'name'       => $name,
            'slug'       => Str::slug($name),
            'category'   => $meta[0],
            'aliases'    => json_encode($meta[1]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        // Chunk to stay under packet limits on shared hosts.
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('skills')->upsert($chunk, uniqueBy: ['slug'], update: ['name', 'category', 'aliases']);
        }
    }
}
