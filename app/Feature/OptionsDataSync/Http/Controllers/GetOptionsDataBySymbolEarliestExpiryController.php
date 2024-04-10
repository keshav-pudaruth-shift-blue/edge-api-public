<?php

namespace App\Feature\OptionsDataSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\OptionsLiveDataLatestRepository;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class GetOptionsDataBySymbolEarliestExpiryController extends Controller
{
    public function __construct(protected OptionsLiveDataLatestRepository $optionsLiveDataLatestRepository)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(): \Illuminate\Support\Collection
    {
        $symbol = request()->route('symbol');

        $earliestExpiryDate = $this->optionsLiveDataLatestRepository->getEarliestExpiryDate($symbol);

        return $this->optionsLiveDataLatestRepository->getOptionsDataBySymbolAndDate($symbol, now(), $earliestExpiryDate);
    }
}
