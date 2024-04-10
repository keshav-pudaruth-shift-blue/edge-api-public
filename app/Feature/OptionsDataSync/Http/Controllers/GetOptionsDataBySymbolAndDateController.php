<?php

namespace App\Feature\OptionsDataSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OptionsLiveDataLatest;
use App\Repositories\OptionsLiveDataLatestRepository;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class GetOptionsDataBySymbolAndDateController extends Controller
{
    public function __construct(protected OptionsLiveDataLatestRepository $optionsLiveDataLatestRepository)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(): \Illuminate\Support\Collection
    {
        $this->validate(request(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d',
        ]);

        $symbol = request()->route('symbol');
        $fromDate = Carbon::createFromFormat('Y-m-d',request()->input('from_date'));
        $toDate = Carbon::createFromFormat('Y-m-d',request()->input('to_date'));

        return $this->optionsLiveDataLatestRepository->getOptionsDataBySymbolAndDate($symbol, $fromDate, $toDate);
    }
}
