module.exports = {
    future: {
        // removeDeprecatedGapUtilities: true,
        // purgeLayersByDefault: true,
    },
    purge: [],
    theme: {
        extend: {},
        flex: {
            "1": "1 1 0%",
            "2": "2 2 0%"
        },
        minWidth: {
            "0": "0",
            "1/4": "25%",
            "1/3": "33%",
            "1/2": "50%",
            "3/4": "75%",
            full: "100%"
        }
    },
    variants: {
        extend: {
            opacity: ["disabled"]
        }
    },
    plugins: []
};
}
