<?php

namespace App\Feature\OpenAI\Services;

use App\Models\OpenAIChatPrompts;
use App\Repositories\OpenAIChatLogRepository;
use App\Repositories\OpenAIChatPromptsRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OpenAIChatService extends OpenAIService
{
    /**
     * @param OpenAIChatLogRepository $openAIChatLogRepository
     * @param OpenAIChatPromptsRepository $openAIChatPromptsRepository
     */
    public function __construct(
        protected OpenAIChatLogRepository $openAIChatLogRepository,
        protected OpenAIChatPromptsRepository $openAIChatPromptsRepository
    )
    {
        parent::__construct();
    }

    /**
     * @param string $prompt
     * @param string $context
     * @return string
     */
    public function start(string $prompt, string $context): string
    {
        //Get prompts
        $prompts = $this->openAIChatPromptsRepository->getByContext($context);
        //Build payload
        $payload = $this->buildPayload($prompt, $prompts);
        //Save request to log
        $chatLogRow = $this->openAIChatLogRepository->getQuery()->create([
            'request' => $payload,
            'context' => $context
        ]);
        //Call OpenAI API
        $response = $this->client->chat()->create($payload);
        //Save response's first choice
        $chatLogRow->update([
            'response' => $response->toArray(),
            'total_tokens' => $response->usage->totalTokens,
        ]);
        //Return response's first choice
        $messageContent = Arr::get(
            $response->toArray(),
            'choices.0.message.content',
            'I am sorry, I do not understand this request. Please ask my creator to train me on this scenario.'
        );

        return $messageContent;
    }

    /**
     * @param string $customPrompt
     * @param Collection $prompts
     * @return array
     */
    protected function buildPayload(string $customPrompt,Collection $prompts): array
    {
        $payload = [
            'model' => env('OPENAI_CHAT_MODEL'),
            'messages' => []
        ];

        //Construct default prompts
        foreach ($prompts as $promptRow) {
            if($promptRow->role === OpenAIChatPrompts::ROLE_USER)
            {
                $payload['messages'][] = [
                    'role' => OpenAIChatPrompts::ROLE_ASSISTANT,
                    'content' => $promptRow->content.' \n'.$customPrompt
                ];
            } else {
                $payload['messages'][] = [
                    'role' => $promptRow->role,
                    'content' => $promptRow->content
                ];
            }
        }

        return $payload;
    }
}
