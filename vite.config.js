import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import fs from 'fs';
import os from "os";
import path from "path";
import { fileURLToPath } from "url";
import Components from "unplugin-vue-components/vite";
import {
    UnpluginVueComponentsResolver,
    UnpluginDirectivesResolver,
    UnpluginModulesResolver,
} from "maz-ui/resolvers";

 
function adresseLocale() {
    const adresses = Object.values(os.networkInterfaces())
        .flat()
        .filter((net) => net && (net.family === "IPv4" || net.family === 4) && !net.internal)
        .map((net) => net.address);

    return adresses.find((adresse) => adresse.startsWith("192.168."))
        ?? adresses.find((adresse) => adresse.startsWith("10."))
        ?? adresses[0]
        ?? "127.0.0.1";
}

const adresseTelephone = adresseLocale();

export default defineConfig({
    plugins: [
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        Components({
            dts: true,
            resolvers: [
                UnpluginVueComponentsResolver(),
                UnpluginDirectivesResolver(),
                UnpluginModulesResolver(),
            ],
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5177,
        origin: `http://${adresseTelephone}:5177`,
        hmr: {
            host: adresseTelephone,
        },
        cors: true,
    },
    resolve: {
        alias: {
            "@": path.resolve(path.dirname(fileURLToPath(import.meta.url)), "resources/js"),
            "~": path.resolve(path.dirname(fileURLToPath(import.meta.url)), "resources"),
        },
    },
}); 
 