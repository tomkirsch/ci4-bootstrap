<script>
    document.addEventListener("lazyloaded", function(e) {
        let el = e.target.closest(".dyn_fadebox");
        if (el) el.classList.add("dyn_lazyloaded");
    });
</script>
<noscript>
    <style type="text/css">
        .dyn_fadebox [data-dyn_lqip="integrated"] {
            opacity: 1 !important;
        }
    </style>
</noscript>