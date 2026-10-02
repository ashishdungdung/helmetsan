import glob
import json
import os
import re

files = glob.glob('data/motorcycles/*.json')

# Genuine Electric identifier check
GENUINE_EV_PATTERNS = [
    'ola_electric', 'ather', 'ultraviolette', 'simple_energy', 'tork', 'revolt',
    'river_ev', 'okinawa', 'ampere', 'hero_electric', 'lml_electric', 'super_soco',
    'energica', 'zero_', 'iqube', 'tvs_x', 'chetak', 'vida', 'ce_04', 'ce_02', 'ce04', 'ce02',
    'vespa_elettrica', 'maeving', 'e_burgman', 'supersoco', 'gogoro', 'niu_mqi',
    'silence_s01', 'yadea_g5', 'em1_e', 'ninja_e_1', 'z_e_1', 'ola_', 'simple_one', 'river_indie'
]

def is_genuine_ev(fname, brand):
    fn = fname.lower()
    b = brand.lower()
    for p in GENUINE_EV_PATTERNS:
        if p in fn or p in b:
            if p == 'hero_electric' and 'motocorp' in b:
                continue
            return True
    return False

# Database of platform specs
PLATFORM_SPECS = [
    (r'activa[-_]6g', 109.5, 7.8, 8.9, 106, 'CVT Automatic', '109.5cc Single-Cylinder 4-Stroke PGM-FI'),
    (r'himalayan[-_]450', 452.0, 40.0, 40.0, 196.0, '6-speed Manual with Slipper Clutch', '452cc Single-Cylinder Liquid-Cooled DOHC 4V (Sherpa 450)'),
    (r'harley[-_]street[-_]glide', 1868.0, 93.0, 158.0, 375.0, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'activa_6g', 109.5, 7.8, 8.9, 106, 'CVT Automatic', '109.5cc Single-Cylinder 4-Stroke PGM-FI'),
    (r'apache_rr_310', 312.2, 34.0, 27.3, 174, '6-speed Manual with Slipper Clutch', '312.2cc Single-Cylinder Liquid-Cooled DOHC'),
    (r'apache_rtr_180', 177.4, 17.0, 15.5, 140, '5-speed Manual', '177.4cc Single-Cylinder Oil-Cooled 2V'),
    (r'apache_rtr_310', 312.2, 35.6, 28.7, 169, '6-speed Manual with Bi-directional Quickshifter', '312.2cc Single-Cylinder Liquid-Cooled DOHC'),
    (r'aprilia_rs_457', 457.0, 47.6, 43.5, 175, '6-speed Manual', '457cc Parallel-Twin Liquid-Cooled DOHC 4V'),
    (r'aprilia_tuono_v4', 1077.0, 175.0, 121.0, 209, '6-speed Manual with Quickshifter', '1077cc 65-Degree V4 Liquid-Cooled DOHC'),
    (r'avenger_cruise_220', 220.0, 19.0, 17.5, 163, '5-speed Manual', '220cc Single-Cylinder Oil-Cooled Twin Spark'),
    (r'benelli_502c', 500.0, 47.0, 45.0, 216, '6-speed Manual', '500cc Parallel-Twin Liquid-Cooled DOHC'),
    (r'benelli_tnt_600i', 600.0, 85.0, 54.6, 231, '6-speed Manual', '600cc Inline-4 Liquid-Cooled DOHC 16V'),
    (r'benelli_trk_502', 500.0, 47.5, 46.0, 235, '6-speed Manual', '500cc Parallel-Twin Liquid-Cooled DOHC'),
    (r'bimota_kb4', 1043.0, 142.0, 111.0, 194, '6-speed Manual', '1043cc Inline-4 Liquid-Cooled DOHC 16V'),
    (r'bimota_tesi_h2', 998.0, 231.0, 141.0, 207, '6-speed Manual', '998cc Supercharged Inline-4 Liquid-Cooled DOHC'),
    (r'r1250gs', 1254.0, 136.0, 143.0, 249, '6-speed Manual', '1254cc Boxer Twin Air/Liquid-Cooled ShiftCam'),
    (r'bonneville_bobber', 1200.0, 78.0, 106.0, 251, '6-speed Manual', '1200cc Parallel-Twin Liquid-Cooled SOHC 8V'),
    (r'bonneville_t120', 1200.0, 80.0, 105.0, 236, '6-speed Manual', '1200cc Parallel-Twin Liquid-Cooled SOHC 8V'),
    (r'b65_scrambler', 652.0, 45.0, 55.0, 218, '5-speed Manual', '652cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'cb750_hornet', 755.0, 90.6, 75.0, 190, '6-speed Manual', '755cc Parallel-Twin Liquid-Cooled 8V Unicam'),
    (r'cfmoto_700cl_x', 693.0, 70.0, 61.0, 196, '6-speed Manual', '693cc Parallel-Twin Liquid-Cooled DOHC'),
    (r'cfmoto_800mt', 799.0, 91.0, 75.0, 231, '6-speed Manual', '799cc Parallel-Twin Liquid-Cooled DOHC (LC8c)'),
    (r'cfmoto_800nk', 799.0, 100.6, 81.0, 186, '6-speed Manual', '799cc Parallel-Twin Liquid-Cooled DOHC (LC8c)'),
    (r'continental_gt_535', 535.0, 29.1, 44.0, 184, '5-speed Manual', '535cc Single-Cylinder Air-Cooled 4-Stroke'),
    (r'ducati_916', 916.0, 114.0, 90.0, 195, '6-speed Manual', '916cc Desmo 90-Degree V-Twin Liquid-Cooled'),
    (r'desertx', 937.0, 110.0, 92.0, 223, '6-speed Manual', '937cc Testastretta 11-Degree L-Twin DOHC'),
    (r'desmosedici_rr', 989.0, 200.0, 116.0, 171, '6-speed Manual', '989cc 90-Degree V4 Desmodromic DOHC'),
    (r'diavel_v4', 1158.0, 168.0, 126.0, 223, '6-speed Manual', '1158cc V4 Granturismo Liquid-Cooled DOHC'),
    (r'hypermotard_698', 659.0, 77.5, 63.0, 151, '6-speed Manual', '659cc Superquadro Mono Single-Cylinder Desmodromic'),
    (r'monster_sp', 937.0, 111.0, 93.0, 186, '6-speed Manual', '937cc Testastretta 11-Degree L-Twin DOHC'),
    (r'multistrada_v2', 937.0, 113.0, 96.0, 225, '6-speed Manual', '937cc Testastretta 11-Degree L-Twin DOHC'),
    (r'multistrada_v4', 1158.0, 170.0, 125.0, 240, '6-speed Manual', '1158cc V4 Granturismo Liquid-Cooled DOHC'),
    (r'panigale_v2', 955.0, 155.0, 104.0, 193, '6-speed Manual', '955cc Superquadro 90-Degree V-Twin Desmodromic'),
    (r'panigale_v4', 1103.0, 215.5, 123.6, 195.5, '6-speed Manual with Quickshifter', '1103cc Desmosedici Stradale 90-Degree V4'),
    (r'scrambler_.*(throttle|icon|nightshift)', 803.0, 73.0, 65.2, 185, '6-speed Manual', '803cc L-Twin Air-Cooled Desmodromic 2V'),
    (r'streetfighter_v2', 955.0, 153.0, 101.4, 199, '6-speed Manual', '955cc Superquadro 90-Degree V-Twin Desmodromic'),
    (r'streetfighter_v4', 1103.0, 208.0, 123.0, 196, '6-speed Manual with Quickshifter', '1103cc Desmosedici Stradale 90-Degree V4'),
    (r'superleggera_v4', 998.0, 224.0, 116.0, 159, '6-speed Manual', '998cc Desmosedici Stradale R 90-Degree V4'),
    (r'xdiavel', 1262.0, 160.0, 127.0, 247, '6-speed Manual', '1262cc Testastretta DVT L-Twin DOHC'),
    (r'ak550', 550.4, 53.0, 56.0, 230, 'CVT Automatic', '550.4cc Parallel-Twin Liquid-Cooled DOHC 8V'),
    (r'maxsym_tl_508', 507.7, 45.5, 49.9, 227, 'CVT Automatic', '507.7cc Parallel-Twin Liquid-Cooled DOHC 8V'),
    (r'f900xr', 895.0, 105.0, 92.0, 219, '6-speed Manual', '895cc Parallel-Twin Liquid-Cooled DOHC'),
    (r'fz25', 249.0, 20.8, 20.1, 153, '5-speed Manual', '249cc Single-Cylinder Air-Oil Cooled SOHC 2V'),
    (r'fz_s|fz_x', 149.0, 12.4, 13.3, 139, '5-speed Manual', '149cc Single-Cylinder Air-Cooled SOHC 2V'),
    (r'fzs_25', 249.0, 20.8, 20.1, 154, '5-speed Manual', '249cc Single-Cylinder Air-Oil Cooled SOHC 2V'),
    (r'g310(gs|r|rr)', 313.0, 34.0, 28.0, 174, '6-speed Manual', '313cc Single-Cylinder Liquid-Cooled DOHC'),
    (r'giorno_50', 49.0, 4.5, 4.1, 81, 'CVT Automatic', '49cc Single-Cylinder Air-Cooled 4-Stroke'),
    (r'gold_wing', 1833.0, 126.0, 170.0, 383, '7-speed Dual Clutch Transmission (DCT)', '1833cc Horizontally-Opposed 6-Cylinder Liquid-Cooled'),
    (r'grand_filano', 125.0, 8.2, 10.3, 101, 'CVT Hybrid Automatic', '125cc Single-Cylinder Air-Cooled Blue Core Hybrid'),
    (r'gsx_8(r|s)', 776.0, 83.0, 78.0, 205, '6-speed Manual with Bi-directional Quickshifter', '776cc Parallel-Twin DOHC 270-Degree Crank'),
    (r'h_d_breakout_117', 1923.0, 102.0, 168.0, 310, '6-speed Cruise Drive Manual', '1923cc Milwaukee-Eight 117 V-Twin Pushrod OHV'),
    (r'h_d_cvo', 1977.0, 115.0, 189.0, 380, '6-speed Cruise Drive Manual', '1977cc Milwaukee-Eight VVT 121 V-Twin Pushrod OHV'),
    (r'h_d_fat_boy_114', 1868.0, 94.0, 155.0, 317, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'h_d_heritage_classic', 1868.0, 94.0, 155.0, 330, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'h_d_hydra_glide', 1868.0, 94.0, 156.0, 322, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'h_d_low_rider_st', 1923.0, 105.0, 168.0, 327, '6-speed Cruise Drive Manual', '1923cc Milwaukee-Eight 117 V-Twin Pushrod OHV'),
    (r'h_d_nightster', 975.0, 89.0, 95.0, 221, '6-speed Manual', '975cc Revolution Max 975T V-Twin Liquid-Cooled DOHC'),
    (r'h_d_road_glide', 1868.0, 93.0, 158.0, 387, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'h_d_street_bob', 1868.0, 94.0, 155.0, 297, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'h_d_x440', 440.0, 27.0, 38.0, 190.5, '6-speed Manual with Assist & Slipper Clutch', '440cc Single-Cylinder Air-Oil Cooled SOHC 2V'),
    (r'street_glide', 1868.0, 93.0, 158.0, 375, '6-speed Cruise Drive Manual', '1868cc Milwaukee-Eight 114 V-Twin Pushrod OHV'),
    (r'hayabusa', 1340.0, 190.0, 150.0, 264, '6-speed Manual with Bi-directional Quickshifter', '1340cc Inline-4 Liquid-Cooled DOHC 16V'),
    (r'karizma_xmr_210', 210.0, 25.5, 20.4, 163.5, '6-speed Manual with Slipper Clutch', '210cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'mavrick_440', 440.0, 27.0, 36.0, 187.0, '6-speed Manual with Assist & Slipper Clutch', '440cc Single-Cylinder Air-Oil Cooled 2V'),
    (r'passion_xtec', 113.2, 9.15, 9.79, 117.0, '4-speed Manual', '113.2cc Single-Cylinder Air-Cooled 4-Stroke'),
    (r'splendor', 97.2, 8.02, 8.05, 112.0, '4-speed Manual', '97.2cc Single-Cylinder Air-Cooled 4-Stroke OHC'),
    (r'hf_deluxe', 97.2, 8.02, 8.05, 110.0, '4-speed Manual', '97.2cc Single-Cylinder Air-Cooled 4-Stroke OHC'),
    (r'himalayan_411', 411.0, 24.3, 32.0, 199.0, '5-speed Manual', '411cc Single-Cylinder Air-Cooled SOHC 2V'),
    (r'himalayan_45(0|2)', 452.0, 40.0, 40.0, 196.0, '6-speed Manual with Slipper Clutch', '452cc Single-Cylinder Liquid-Cooled DOHC 4V (Sherpa 450)'),
    (r'hornet_2_0', 184.4, 17.2, 15.9, 142.0, '5-speed Manual', '184.4cc Single-Cylinder Air-Cooled PGM-FI'),
    (r'hp2_enduro', 1170.0, 105.0, 115.0, 195.0, '6-speed Manual', '1170cc Boxer Twin Air/Oil-Cooled DOHC'),
    (r'husqvarna_norden_901', 889.0, 105.0, 100.0, 219.0, '6-speed Manual with Easy Shift', '889cc Parallel-Twin Liquid-Cooled DOHC 8V'),
    (r'husqvarna_svartpilen_401', 398.6, 45.0, 39.0, 159.0, '6-speed Manual with Easy Shift', '398.6cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'husqvarna_svartpilen_801', 799.0, 105.0, 87.0, 181.0, '6-speed Manual with Easy Shift', '799cc Parallel-Twin Liquid-Cooled DOHC 8V'),
    (r'husqvarna_701', 692.7, 74.0, 73.5, 147.0, '6-speed Manual', '692.7cc Single-Cylinder Liquid-Cooled SOHC 4V'),
    (r'indian_challenger', 1768.0, 122.0, 178.0, 381.0, '6-speed Manual', '1768cc PowerPlus Liquid-Cooled 60-Degree V-Twin'),
    (r'indian_chief', 1890.0, 88.0, 162.0, 304.0, '6-speed Manual', '1890cc Thunderstroke 116 Air-Cooled 49-Degree V-Twin'),
    (r'indian_chieftain', 1890.0, 92.0, 171.0, 373.0, '6-speed Manual', '1890cc Thunderstroke 116 Air-Cooled 49-Degree V-Twin'),
    (r'indian_pursuit', 1768.0, 122.0, 178.0, 416.0, '6-speed Manual', '1768cc PowerPlus Liquid-Cooled 60-Degree V-Twin'),
    (r'indian_scout', 1250.0, 105.0, 108.0, 243.0, '6-speed Manual', '1250cc SpeedPlus Liquid-Cooled 60-Degree V-Twin'),
    (r'indian_springfield', 1890.0, 92.0, 171.0, 376.0, '6-speed Manual', '1890cc Thunderstroke 116 Air-Cooled 49-Degree V-Twin'),
    (r'jawa_42', 294.72, 27.3, 26.8, 182.0, '6-speed Manual with Assist Clutch', '294.72cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'jawa_perak', 334.0, 30.6, 32.74, 185.0, '6-speed Manual', '334cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'jupiter_zx', 109.7, 7.8, 8.8, 107.0, 'CVT Automatic', '109.7cc Single-Cylinder Air-Cooled 4-Stroke'),
    (r'klx140r', 144.0, 11.0, 12.0, 99.0, '5-speed Manual', '144cc Single-Cylinder Air-Cooled 4-Stroke SOHC'),
    (r'klx230', 233.0, 18.1, 18.3, 139.0, '6-speed Manual', '233cc Single-Cylinder Air-Cooled 4-Stroke SOHC'),
    (r'ktm_1290_super', 1301.0, 180.0, 140.0, 210.0, '6-speed Manual with Quickshifter+', '1301cc 75-Degree V-Twin Liquid-Cooled DOHC (LC8)'),
    (r'ktm_1390_super', 1350.0, 190.0, 145.0, 200.0, '6-speed Manual with Quickshifter+', '1350cc 75-Degree V-Twin Liquid-Cooled DOHC (LC8)'),
    (r'ktm_390', 398.6, 45.3, 39.0, 168.0, '6-speed Manual with Quickshifter+', '398.6cc Single-Cylinder Liquid-Cooled DOHC 4V (LC4c)'),
    (r'ktm_690', 692.7, 74.0, 73.5, 147.0, '6-speed Manual with Quickshifter+', '692.7cc Single-Cylinder Liquid-Cooled SOHC (LC4)'),
    (r'ktm_790', 799.0, 105.0, 87.0, 174.0, '6-speed Manual with Quickshifter+', '799cc Parallel-Twin Liquid-Cooled DOHC 8V (LC8c)'),
    (r'ktm_rc_390', 373.2, 43.5, 37.0, 172.0, '6-speed Manual with Quickshifter+', '373.2cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'meguro_k3', 773.0, 52.0, 62.9, 227.0, '5-speed Manual with Slipper Clutch', '773cc Vertical-Twin Air-Cooled SOHC Bevel-Gear Drive'),
    (r'moto_guzzi_v7', 853.0, 65.0, 73.0, 218.0, '6-speed Manual with Shaft Drive', '853cc Transverse 90-Degree V-Twin Air-Cooled 2V'),
    (r'moto_guzzi_v85', 853.0, 76.0, 82.0, 230.0, '6-speed Manual with Shaft Drive', '853cc Transverse 90-Degree V-Twin Air-Cooled Titanium Valves'),
    (r'moto_guzzi_v9', 853.0, 65.0, 73.0, 210.0, '6-speed Manual with Shaft Drive', '853cc Transverse 90-Degree V-Twin Air-Cooled 2V'),
    (r'mt_03', 321.0, 42.0, 29.5, 167.0, '6-speed Manual', '321cc Parallel-Twin Liquid-Cooled DOHC 4V'),
    (r'mt_07', 689.0, 73.4, 67.0, 184.0, '6-speed Manual', '689cc Parallel-Twin Liquid-Cooled DOHC CP2 Engine'),
    (r'mt_09', 890.0, 119.0, 93.0, 193.0, '6-speed Manual with Quickshifter', '890cc Inline-3 Liquid-Cooled DOHC CP3 Engine'),
    (r'mt_10', 998.0, 165.9, 112.0, 214.0, '6-speed Manual with Quickshifter', '998cc Inline-4 Liquid-Cooled DOHC CP4 Crossplane Engine'),
    (r'mt_15', 155.0, 18.4, 14.1, 141.0, '6-speed Manual with Assist & Slipper Clutch', '155cc Single-Cylinder Liquid-Cooled SOHC 4V with VVA'),
    (r'ninja_h2', 998.0, 200.0, 137.3, 260.0, '6-speed Dog-Ring Manual with Quickshifter', '998cc Supercharged Inline-4 Liquid-Cooled DOHC 16V'),
    (r'ninja_zx_10r', 998.0, 203.0, 114.9, 207.0, '6-speed Cassette Manual with Quickshifter', '998cc Inline-4 Liquid-Cooled DOHC 16V Finger-Follower Valve Train'),
    (r'ninja_zx_4r', 399.0, 77.0, 39.0, 188.0, '6-speed Manual with Quickshifter', '399cc Inline-4 Liquid-Cooled DOHC 16V High-Revving 15000+ RPM'),
    (r'ninja_zx_6r', 636.0, 124.0, 69.0, 198.0, '6-speed Manual with Quickshifter', '636cc Inline-4 Liquid-Cooled DOHC 16V'),
    (r'norton_v4sv', 1200.0, 185.0, 125.0, 193.0, '6-speed Manual with Billet Quickshifter', '1200cc 72-Degree V4 Liquid-Cooled DOHC 16V'),
    (r'pg_1', 114.0, 8.8, 9.5, 107.0, '4-speed Semi-Automatic', '114cc Single-Cylinder Air-Cooled SOHC 2V'),
    (r'piaggio_mp3_530', 530.0, 44.2, 50.0, 280.0, 'CVT Automatic with Reverse Gear', '530cc Single-Cylinder Liquid-Cooled hpe 4-Stroke'),
    (r'pleasure_plus', 110.9, 8.0, 8.7, 104.0, 'CVT Automatic', '110.9cc Single-Cylinder Air-Cooled 4-Stroke'),
    (r'pulsar_220f', 220.0, 20.4, 18.55, 160.0, '5-speed Manual', '220cc Single-Cylinder Oil-Cooled Twin Spark 2V'),
    (r'r12_ninet', 1170.0, 109.0, 115.0, 227.0, '6-speed Manual with Shaft Drive', '1170cc Boxer Twin Air/Oil-Cooled DOHC 4V'),
    (r'r15|yzf_r15', 155.0, 18.4, 14.2, 142.0, '6-speed Manual with Assist & Slipper Clutch', '155cc Single-Cylinder Liquid-Cooled SOHC 4V with VVA'),
    (r'r18', 1802.0, 91.0, 158.0, 345.0, '6-speed Manual with Exposed Drive Shaft', '1802cc Big Boxer Twin Air/Oil-Cooled OHV Pushrod'),
    (r'r90s', 898.0, 67.0, 76.0, 215.0, '5-speed Manual with Shaft Drive', '898cc Boxer Twin Air-Cooled OHV 2V'),
    (r'r_ninet', 1170.0, 109.0, 116.0, 221.0, '6-speed Manual with Shaft Drive', '1170cc Boxer Twin Air/Oil-Cooled DOHC 4V'),
    (r'rocket_3', 2458.0, 167.0, 221.0, 291.0, '6-speed Manual with Shaft Drive', '2458cc Inline-3 Longitudinal Liquid-Cooled DOHC'),
    (r'ronin_225', 225.9, 20.4, 19.93, 160.0, '5-speed Manual with Assist & Slipper Clutch', '225.9cc Single-Cylinder Oil-Cooled SOHC 4V'),
    (r'scooty_pep', 87.8, 5.4, 6.5, 93.0, 'CVT Automatic', '87.8cc Single-Cylinder Air-Cooled 4-Stroke Ecothrust'),
    (r'scram_411', 411.0, 24.3, 32.0, 185.0, '5-speed Manual', '411cc Single-Cylinder Air-Cooled SOHC 2V (LS410)'),
    (r'star_city_plus', 109.7, 8.19, 8.7, 115.0, '4-speed Manual', '109.7cc Single-Cylinder Air-Cooled 4-Stroke ETFi'),
    (r'thruxton_rs', 1200.0, 105.0, 112.0, 197.0, '6-speed Manual with Torque-Assist Clutch', '1200cc Parallel-Twin High Power Liquid-Cooled SOHC'),
    (r'tiger_900', 888.0, 95.2, 87.0, 201.0, '6-speed Manual with Slip-and-Assist Clutch', '888cc Inline-3 Liquid-Cooled DOHC 12V T-Plane Crank'),
    (r'tmax_560', 562.0, 47.6, 55.7, 220.0, 'V-Belt Automatic (CVT)', '562cc Forward-Inclined Parallel-Twin Liquid-Cooled DOHC 4V'),
    (r'tracer_7', 689.0, 73.4, 68.0, 203.0, '6-speed Manual', '689cc Parallel-Twin Liquid-Cooled DOHC CP2 Engine'),
    (r'tracer_9', 890.0, 119.0, 93.0, 223.0, '6-speed Manual with Quickshifter', '890cc Inline-3 Liquid-Cooled DOHC CP3 Engine'),
    (r'v_strom_1050', 1037.0, 107.0, 100.0, 252.0, '6-speed Manual with Bi-directional Quickshifter', '1037cc 90-Degree V-Twin Liquid-Cooled DOHC'),
    (r'vincent_black_shadow', 998.0, 55.0, 80.0, 208.0, '4-speed Manual', '998cc 50-Degree V-Twin Air-Cooled OHV Pushrod'),
    (r'voge_525dsx', 494.0, 47.6, 44.5, 206.0, '6-speed Manual with Slipper Clutch', '494cc Parallel-Twin Liquid-Cooled DOHC 8V (KEL500F)'),
    (r'xsr900', 890.0, 119.0, 93.0, 193.0, '6-speed Manual with Quickshifter', '890cc Inline-3 Liquid-Cooled DOHC CP3 Engine'),
    (r'yamaha[-_]r1|yzf_r1m', 998.0, 200.0, 113.3, 201.0, '6-speed Manual with Quickshifter', '998cc Inline-4 Liquid-Cooled DOHC Crossplane CP4'),
    (r'yezdi_adventure', 334.0, 30.2, 29.9, 188.0, '6-speed Manual with Assist Clutch', '334cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'yezdi_roadster', 334.0, 29.7, 29.0, 184.0, '6-speed Manual with Assist Clutch', '334cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'yezdi_scrambler', 334.0, 29.1, 28.2, 182.0, '6-speed Manual with Assist Clutch', '334cc Single-Cylinder Liquid-Cooled DOHC 4V'),
    (r'yzf_r3', 321.0, 42.0, 29.5, 169.0, '6-speed Manual', '321cc Parallel-Twin Liquid-Cooled DOHC 4V'),
    (r'yzf_r7', 689.0, 73.4, 67.0, 188.0, '6-speed Manual with Assist & Slipper Clutch', '689cc Parallel-Twin Liquid-Cooled DOHC CP2 Engine'),
    (r'z_h2', 998.0, 200.0, 137.0, 239.0, '6-speed Dog-Ring Manual with Quickshifter', '998cc Supercharged Balanced Supercharged Inline-4 DOHC 16V')
]

remediated_count = 0
ev_preserved_count = 0
unmatched = []

for f in files:
    try:
        with open(f) as fp:
            d = json.load(fp)
    except:
        continue

    cc = d.get('displacement_cc', 0)
    pm = d.get('intelligence_matrix', {}).get('powertrain_and_chassis', {})
    disp = str(pm.get('displacement', '')).lower()
    brand = d.get('brand') or d.get('make') or ''
    fn = os.path.basename(f)

    if is_genuine_ev(fn, brand):
        ev_preserved_count += 1
        # Ensure EV has correct powertrain
        if d.get('powertrain') != 'electric':
            d['powertrain'] = 'electric'
            d['fuel_type'] = 'electric'
            with open(f, 'w') as fp:
                json.dump(d, fp, indent=2)
        continue

    # If it is an ICE bike that had cc == 0 or electric label:
    if cc == 0 or 'electric' in disp:
        # Match against PLATFORM_SPECS
        matched_spec = None
        for pattern, p_cc, p_hp, p_nm, p_kg, p_trans, p_desc in PLATFORM_SPECS:
            if re.search(pattern, fn.lower()):
                matched_spec = (p_cc, p_hp, p_nm, p_kg, p_trans, p_desc)
                break

        if matched_spec:
            p_cc, p_hp, p_nm, p_kg, p_trans, p_desc = matched_spec
            d['displacement_cc'] = p_cc
            d['power_hp'] = p_hp
            d['torque_nm'] = p_nm
            d['curb_weight_kg'] = p_kg
            d['transmission'] = p_trans
            d['powertrain'] = 'internal combustion engine'
            d['fuel_type'] = 'petrol'

            # Update intelligence_matrix
            if 'intelligence_matrix' not in d:
                d['intelligence_matrix'] = {}
            if 'powertrain_and_chassis' not in d['intelligence_matrix']:
                d['intelligence_matrix']['powertrain_and_chassis'] = {}

            p_matrix = d['intelligence_matrix']['powertrain_and_chassis']
            p_matrix['displacement'] = p_desc
            p_matrix['power_hp'] = p_hp
            p_matrix['torque_nm'] = p_nm
            p_matrix['curb_weight_kg'] = p_kg
            p_matrix['transmission'] = p_trans
            p_matrix['power_to_weight'] = f"{round((p_hp / p_kg) * 100, 1)} HP/100kg"

            with open(f, 'w') as fp:
                json.dump(d, fp, indent=2)
            remediated_count += 1
        else:
            unmatched.append((f, brand))

print(f'Remediated ICE motorcycles: {remediated_count}')
print(f'Preserved genuine EVs: {ev_preserved_count}')
print(f'Unmatched remaining: {len(unmatched)}')
if unmatched:
    for u in unmatched[:10]:
        print('  Unmatched:', u)
