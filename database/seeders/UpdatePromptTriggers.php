<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UpdatePromptTriggers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Update business hours prompt to be more specific
        Prompt::where('prompt_content', 'LIKE', '%business hours are Monday-Friday%')
            ->update([
                'trigger_phrase' => 'business hours, hours, what time, open, close, schedule, operating hours, when open, when close',
                'priority' => 9 // Higher priority than company info
            ]);
        
        // Update company info prompt to be less broad
        Prompt::where('prompt_content', 'LIKE', '%leading manufacturer of commercial%')
            ->update([
                'trigger_phrase' => 'about company, what is glacier, company info, glacier megafridge, who are you, about glacier',
                'priority' => 7 // Lower priority than business hours
            ]);
        
        // Make help prompt more specific
        Prompt::where('prompt_content', 'LIKE', '%describe your issue in detail%')
            ->update([
                'trigger_phrase' => 'help me, need help, support, problem, issue, not working, broken, trouble, assistance',
                'priority' => 8
            ]);
    }
}
