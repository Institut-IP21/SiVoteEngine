<div class="w-full px-6 pb-6">
    <h2 class="font-bold text-xl flex justify-between items-baseline">
        Glasovalna koda
        <svg id="info-icon" width="20px" height="20px" viewBox="0 0 24 24" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_429_11160)">
                <circle cx="12" cy="11.9999" r="9" stroke="#292929" stroke-width="2.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                <rect x="12" y="8" width="0.01" height="0.01" stroke="#292929" stroke-width="3.75"
                    stroke-linejoin="round" />
                <path d="M12 12V16" stroke="#292929" stroke-width="2.5" stroke-linecap="round"
                    stroke-linejoin="round" />
            </g>
            <defs>
                <clipPath id="clip0_429_11160">
                    <rect width="24" height="24" fill="white" />
                </clipPath>
            </defs>
        </svg>
    </h2>
    <p id="info-text" class="text-sm sm:text-base mb-4 hidden">
        Koda, ki ste jo prejeli (in je tudi del povezave, ki vas je pripeljala sem) je način, kako sistem ve, da imate
        pravico glasovati brez, da bi vedel kdo ste. Zato je pomembno, da jo hranite zase in jo ne delite z nikomer. S
        kodo lahko po koncu glasovanja preverite ali so bili vaši glasovi pravilno zabeleženi v izpis vseh glasov.
    </p>
    <input name="code" readonly x-bind:type="show ? 'text' : 'password'" x-data="{ show: false }"
        x-on:mouseover="show = true" x-on:mouseout="show = false"
        class="shadow appearance-none border rounded w-full py-2 px-3 leading-tight focus:outline-none" id="code"
        type="password" placeholder="koda" value="{{ $code }}">
</div>

<script>
    document.getElementById('info-icon').addEventListener('click', function() {
        var infoText = document.getElementById('info-text');
        infoText.classList.toggle('hidden');
    });
</script>