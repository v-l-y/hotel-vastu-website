import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),"..");
const sources=["css/variables.css","css/base.css","css/components.css","css/layout.css","css/responsive.css"];
const bundle=sources.map(file=>`/* ${file} */\n${fs.readFileSync(path.join(root,file),"utf8").trim()}`).join("\n\n")+"\n";
fs.writeFileSync(path.join(root,"css/site.css"),bundle);
console.log("Built css/site.css from",sources.length,"source files.");
