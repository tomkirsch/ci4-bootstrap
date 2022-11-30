<style type="text/css">
    [style*="--aspect-ratio"]> :first-child,
    [style*="--aspect-ratio"]>picture {
        width: 100%;
    }

    [style*="--aspect-ratio"] img {
        max-width: 100%;
        height: auto;
    }

    [dyn_wrapper_orient="landscape"] [data-dyn_src_orient="portrait"] img {
        height: 100%;
        width: auto;
        max-width: none;
    }

    [dyn_wrapper_orient="portrait"] [data-dyn_src_orient="landscape"] img {
        max-width: 100%;
        height: auto;
        max-height: none;
    }

    [data-dyn_fit="crop"] {
        overflow: hidden;
    }

    [data-dyn_fit="crop"][dyn_wrapper_orient="portrait"] img {
        max-width: none !important;
        width: auto;
        height: 100%;
    }

    [data-dyn_fit="crop"][dyn_wrapper_orient="landscape"] img {
        max-width: none !important;
        width: 100%;
        height: auto;
    }

    [style*="--aspect-ratio"]>:first-child[data-dyn_crop] {
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    @supports (--custom:property) {
        [style*="--aspect-ratio"] {
            position: relative;
        }

        [style*="--aspect-ratio"]::before {
            content: "";
            display: block;
            padding-bottom: calc(100% / (var(--aspect-ratio)));
        }

        [style*="--aspect-ratio"]> :first-child,
        [style*="--aspect-ratio"]>picture {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }

    [data-dyn_lqip="separate"] {
        transition: opacity 400ms;
    }

    .dyn_lazyloaded [data-dyn_lqip="separate"] {
        opacity: 0;
    }
</style>
<noscript>
    <style type="text/css">
        [data-dyn_lqip="separate"] {
            opacity: 0;
        }
    </style>
</noscript>