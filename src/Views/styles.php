<?php if (!empty($withTag)) : ?>
    <style type="text/css">
        <?php endif ?>[style*="--aspect-ratio"]> :first-child,
        [style*="--aspect-ratio"]>picture {
            width: 100%;
        }

        [style*="--aspect-ratio"] img {
            max-width: 100%;
            height: auto;
        }

        [data-dyn_fit="contain"][dyn_wrapper_orient="landscape"][data-dyn_src_orient="portrait"] img {
            height: 100%;
            width: auto;
            max-width: none;
        }

        [data-dyn_fit="crop"] {
            overflow: hidden;
        }

        [data-dyn_fit="crop"] [data-dyn_lqip="separate"] {
            max-width: none;
            width: 100%;
            height: 100%;
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
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .dyn_fadebox [data-dyn_lqip="integrated"] {
            opacity: 0;
            transition: opacity 400ms;
        }

        .dyn_fadebox.dyn_lazyloaded [data-dyn_lqip="integrated"],
        .dyn_fadebox .dyn_lazyloaded [data-dyn_lqip="integrated"] {
            opacity: 1;
        }

        <?php if (!empty($withTag)) : ?>
    </style>
<?php endif ?>