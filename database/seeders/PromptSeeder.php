<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $prompts = [
            // Greetings - High Priority
            [
                'trigger_phrase' => 'hello, hi, good morning, good afternoon, hey, greetings',
                'prompt_content' => 'Hello! I\'m GAB, your Glacier Megafridge assistant. How can I help you today?',
                'prompt_type' => 'response',
                'priority' => 10,
            ],
            
            // Company Information - High Priority
            [
                'trigger_phrase' => 'about, company, glacier megafridge, who are you, what is glacier',
                'prompt_content' => 'Glacier Megafridge Inc. is a leading manufacturer of commercial and industrial refrigeration systems. We specialize in energy-efficient cooling solutions for restaurants, supermarkets, and food service industries. We\'ve been serving businesses with reliable refrigeration solutions for over 20 years.',
                'prompt_type' => 'response',
                'priority' => 9,
            ],
            
            // Business Hours - High Priority
            [
                'trigger_phrase' => 'hours, time, open, schedule, when open, business hours',
                'prompt_content' => 'Our business hours are Monday-Friday 8:00 AM to 6:00 PM, and Saturday 9:00 AM to 2:00 PM. We are closed on Sundays and major holidays. For emergency service, please describe your issue and I can help you create a priority support ticket.',
                'prompt_type' => 'response',
                'priority' => 8,
            ],
            
            // Contact Information - High Priority
            [
                'trigger_phrase' => 'contact, phone, email, address, location, how to reach',
                'prompt_content' => 'You can reach us through this chat system for immediate assistance. For urgent technical support or service requests, I can help you create a support ticket. Our main office handles all customer communications through our support system.',
                'prompt_type' => 'response',
                'priority' => 7,
            ],
            
            // Help/Support - Very High Priority
            [
                'trigger_phrase' => 'help, support, problem, issue, not working, broken, trouble',
                'prompt_content' => 'I\'m here to help! Please describe your issue in detail. Common issues I can assist with include:\n\n• Temperature problems\n• Unusual noises\n• Power issues\n• Maintenance questions\n• Parts information\n\nFor complex technical issues, I can also help you create a support ticket.',
                'prompt_type' => 'response',
                'priority' => 10,
            ],
            
            // Product Information
            [
                'trigger_phrase' => 'products, models, types, what do you sell, refrigerators',
                'prompt_content' => 'We manufacture a wide range of refrigeration systems:\n\n• Commercial Reach-in Refrigerators\n• Walk-in Coolers and Freezers\n• Display Cases\n• Ice Machines\n• Prep Tables\n• Specialized Food Service Equipment\n\nWould you like information about a specific type of refrigeration system?',
                'prompt_type' => 'response',
                'priority' => 6,
            ],
            
            // Warranty Information
            [
                'trigger_phrase' => 'warranty, guarantee, coverage, how long, warranty period',
                'prompt_content' => 'All Glacier Megafridge products come with a comprehensive 2-year warranty covering parts and labor. Extended warranty options are available for up to 5 years. The warranty covers manufacturing defects and normal wear components. For specific warranty information, please provide your model number and purchase date.',
                'prompt_type' => 'response',
                'priority' => 7,
            ],
            
            // Basic Troubleshooting
            [
                'trigger_phrase' => 'not cooling, not cold enough, temperature, warm',
                'prompt_content' => 'For cooling issues, please check these common causes:\n\n1. **Power Supply**: Ensure the unit is properly plugged in\n2. **Temperature Settings**: Verify thermostat is set correctly\n3. **Air Flow**: Check that vents aren\'t blocked\n4. **Door Seals**: Make sure doors close properly\n5. **Condenser Coils**: May need cleaning if dusty\n\nIf these steps don\'t resolve the issue, I can help you create a service ticket for technical support.',
                'prompt_type' => 'response',
                'priority' => 9,
            ],
            
            // Maintenance
            [
                'trigger_phrase' => 'maintenance, cleaning, service, upkeep, care',
                'prompt_content' => 'Regular maintenance keeps your refrigeration system running efficiently:\n\n**Monthly:**\n• Clean condenser coils\n• Check door seals\n• Clean interior and exterior\n\n**Quarterly:**\n• Inspect drainage\n• Check temperature accuracy\n• Lubricate moving parts\n\n**Annually:**\n• Professional service inspection\n• Refrigerant level check\n• Electrical connections review\n\nWould you like specific maintenance instructions for your model?',
                'prompt_type' => 'response',
                'priority' => 6,
            ],
            
            // Thank you responses
            [
                'trigger_phrase' => 'thank you, thanks, appreciate, helpful',
                'prompt_content' => 'You\'re welcome! I\'m happy to help with any questions about Glacier Megafridge products or services. Is there anything else I can assist you with today?',
                'prompt_type' => 'response',
                'priority' => 5,
            ],

            // Founder Information
            [
                'trigger_phrase' => 'ceo, who is the ceo, founder, who started, who created, who is the founder',
                'prompt_content' => 'Glacier Megafridge was founded by Arturo Jose C. Yan in 2005. With a background in mechanical engineering and a passion for sustainable technology, Arturo envisioned creating energy-efficient refrigeration solutions for the food service industry. Under his leadership, the company has grown to become a trusted name in commercial refrigeration.',
                'prompt_type' => 'response',
                'priority' => 5,
            ],
        ];

        foreach ($prompts as $prompt) {
            Prompt::create($prompt);
        }
    }
}
