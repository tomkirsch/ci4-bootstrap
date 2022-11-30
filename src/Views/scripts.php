<script>
    document.addEventListener("lazyloaded", function(e) {
        let el = e.target.closest(".dyn_fadebox");
        if (el) el.classList.add("dyn_lazyloaded");
    });
</script>