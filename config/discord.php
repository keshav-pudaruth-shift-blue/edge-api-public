<?php

/**
 * Remove https://discord.com/api/webhooks/ from the webhook url
 */

use App\Models\OptionsChainWatchlist;

return [
    'webhooks' => [
        OptionsChainWatchlist::WATCHLIST_TYPE_0DTE => [
            'spxw' => '1181495523711135755/E08LqOO54inpRJnH3t0FvstKEBrIjKtzdct18KwhZTCw6vFO4XaPut1_mI81xig84SbD',
            'spy' => '1181499663795359874/YLRNsqGYAz2r6rVRTqAjlrM1METM0COL1sYdU20bkck8WP09diOKn0DupdjU7CY9_Xjw',
            'qqq' => '1181499787397308516/aAFtwS-kA1Gzf9RUTWHUx6M-PfEC4S-RztxzzLRqx2DW5GzSNSREg8cfiItrE2JOX7ek',
            'iwm' => '1181488970740269147/e2sN1fz7JXtADxrRiDm7EmsrKZQwYZMBOEgHkBKEG34D1Pa3l_rF31jbS4yJ-lxR19Kf',
            'lounge' => '1181543776573997086/2PiULMUg_kBh3fbC_P7po4mRRjkJzNu30AQOHdoyWK-1kT1zVm-4W4sIEnR7I5kxGn5Q',
        ],
        OptionsChainWatchlist::WATCHLIST_TYPE_1DTE => [
            'spxw' => '1181495942432690227/YfOqYBqgN1EvQpvzMO142R1Zmtb1Em7sU3y3Tog4G8oqB2dmfo-wNal43a1dn_SWWmwk',
            'spy' => '1181489243936260178/i3BMAXF2_8hf5o7c2Jwtdtz_qqvJneU858PLXBVQeiKSsep9ROIuVOYh5ZVg-AtfxZab',
            'qqq' => '1181491048778838016/0npR69Mr340u4Q2CTSHlAqngsfDmhL7Ktu_KBgLN5Jg0XM19V1oKQschOneFCfoYM7yl',
            'iwm' => '1181500041282732043/ng9rYjpdGxg2vkd67z1rkGgVaXWAWMJegf2YCvddh1g4TfN7LtSUwRVhSNq1HmCOJHX4',
            'lounge' => '1181543911768997908/l0ESVFcSFVxSbBorQrv2azqOjpjJpmLFuxauWrve1UokEZ1WthuPcUgGM8Rk7JLsDEeB',
        ],
        OptionsChainWatchlist::WATCHLIST_TYPE_OPEX => [

        ],
        'analyst-ratings' => '1184444465226727545/GFdYuukV3M9s4KJ-aM9SOUIV77_ZSoSwEe6NjkdPzBcEuI9XsQpdozSKxZ_u6CUsP3R_'
    ]
];
