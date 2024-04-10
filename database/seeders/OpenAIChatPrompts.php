<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OpenAIChatPrompts extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\OpenAIChatPrompts::insert([
            'id' => 1,
            'context' => \App\Models\OpenAIChatPrompts::CONTEXT_TWITTER_TRADES,
            'role' => \App\Models\OpenAIChatPrompts::ROLE_SYSTEM,
            'content' => "I want you to be a master option trader that reads texts and identifies option trades. Some texts will not have any option trades. If no option trades are found in the text, say \"No trades found\". You identify option trades through the following mandatory criteria:
                            1. action: buy to open or sell to close
                            2. option type: `put` or `call`,
                            3. strikes that are numeric, including its optional separator `/`
                            4. stock symbol that are in all capital letters.
                            5. expiry date: 0dte = today, 1dte = tomorrow",
            'is_active' => true,
        ]);

        \App\Models\OpenAIChatPrompts::insert([
            'id' => 2,
            'context' => \App\Models\OpenAIChatPrompts::CONTEXT_TWITTER_TRADES,
            'role' => \App\Models\OpenAIChatPrompts::ROLE_USER,
            'content' => "You will then extract the keywords and display them in the following format: <action> <stock symbol>  <option type> <strikes> <expiry date>

Be as specific as possible.
Focus on the buying and selling of the option trades. Ignore the rest.
",
            'is_active' => true,
        ]);
    }
}
