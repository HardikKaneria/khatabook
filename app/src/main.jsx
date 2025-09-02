import React from "react";
import ReactDOM from "react-dom/client";
import WrappedApp from "./App";
import "./index.css";
import "./theme.css";

import { ConfigProvider } from "antd";
import { FontSizeOutlined } from "@ant-design/icons";

const violetTheme = {
    token: {
        // brand
        colorPrimary: "#6C5CE7",
        colorPrimaryHover: "#5A4BD4",
        colorPrimaryActive: "#4B3FC0",

        // link buttons & <a>
        colorLink: "#6C5CE7",
        colorLinkHover: "#5A4BD4",
        colorLinkActive: "#4B3FC0",

        // base
        colorTextBase: "#121212",
        colorBgBase: "#FDFDFD",
        borderRadius: 8,
        fontFamily: "'Urbanist', 'Lato', system-ui, sans-serif",

        // (optional) some components use info color for “primary-like” UI
        colorInfo: "#6C5CE7",

        fontSize: 16,   // ⬅️ all component base text = 16px
        lineHeight: 1.5, // ⬅️ default text line-height
    },
    components: {
        Button: {
            borderRadius: 8,
            FontSizeOutlined: 16,
            fontWeight: 700,
            controlHeight: 40,
            // make link buttons text-only (no hover bg)
            linkHoverBg: "transparent",
            linkActiveBg: "transparent",
            linkHoverBorderColor: "transparent",
            linkActiveBorderColor: "transparent",
            linkBorderColor: "transparent",
            lineHeight: 1,
            dangerHoverBg: "#ff4d4f",
            dangerActiveBg: "#ff4d4f55",
        },
    },
};


const rootElement = document.getElementById("root");
if (rootElement) {
    ReactDOM.createRoot(rootElement).render(
        <ConfigProvider theme={violetTheme}>
            <WrappedApp />
        </ConfigProvider>
    );
} else {
    console.error("Could not find #root element to mount React.");
}
