import fs from "node:fs";
import path from "node:path";

const root=process.cwd();
const htmlFiles=fs.readdirSync(root).filter(f=>f.endsWith(".html"));

const headerOld='<a class="brand" href="index.html">Hotel <span>Vastu Premium</span></a>';
const headerNew='<a class="brand brand-logo" href="index.html" aria-label="Hotel Vastu Premium home"><img src="images/branding/hotel-vastu-logo.png" width="300" height="81" alt="Hotel Vastu Premium"></a>';
const footerOld='<h3>Hotel Vastu Premium</h3>';
const footerNew='<a class="footer-brand-logo" href="index.html" aria-label="Hotel Vastu Premium home"><img src="images/branding/hotel-vastu-logo.png" width="300" height="81" alt="Hotel Vastu Premium"></a>';
const canonicalTagline='Comfortable stays near RPS More, Patna.';

for(const file of htmlFiles){
  const full=path.join(root,file);
  let html=fs.readFileSync(full,"utf8");

  html=html
    .replaceAll('<meta name="theme-color" content="#17201b">','<meta name="theme-color" content="#211a16">')
    .replaceAll(headerOld,headerNew)
    .replaceAll(footerOld,footerNew)
    .replaceAll('Comfortable stays at RPS More, Danapur, Patna.',canonicalTagline)
    .replaceAll('Comfortable stays at RPS More, Patna.',canonicalTagline);

  const robots=(html.match(/<meta name="robots" content="([^"]*)">/i)?.[1]||"").toLowerCase();
  const indexable=file!=="404.html"&&!robots.includes("noindex");
  const hasOgImage=/<meta property="og:image" content="[^"]+">/i.test(html);
  if(indexable&&!hasOgImage){
    html=html.replace(
      '<meta property="og:site_name" content="Hotel Vastu Premium">',
      '<meta property="og:site_name" content="Hotel Vastu Premium"><meta property="og:image" content="https://hotelvastu.com/images/hotel/og-hotel-vastu.webp">'
    );
  }
  if(indexable){
    html=html.replace(
      /<meta property="og:image" content="https:\/\/hotelvastu\.com\/images\/temp\/[^"]+\.svg">/i,
      '<meta property="og:image" content="https://hotelvastu.com/images/hotel/og-hotel-vastu.webp">'
    );
  }

  if(file==="luxury-room.html"){
    html=html.replace(/<script type="application\/ld\+json">\{[^<]*"@type":"HotelRoom"[^<]*\}<\/script>/g,"");
  }

  fs.writeFileSync(full,html);
}

const layoutPath=path.join(root,"css/layout.css");
let layout=fs.readFileSync(layoutPath,"utf8");
layout=layout
  .replace(/\.site-header\.is-scrolled \.brand\{width:158px\}/g,'.site-header.is-scrolled .brand-logo img{width:158px}')
  .replace(/\.brand\{display:block;width:168px;height:52px;font-size:0;line-height:0;background:url\("\.\.\/images\/branding\/hotel-vastu-logo\.png"\) center\/contain no-repeat;filter:drop-shadow\(0 4px 12px rgba\(33,26,22,\.08\)\);transition:width \.24s ease,filter \.24s ease\}/g,
    '.brand{display:inline-flex;align-items:center;line-height:0}.brand-logo img{display:block;width:168px;height:auto;max-height:52px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(33,26,22,.08));transition:width .24s ease,filter .24s ease}')
  .replace(/\.footer h3\{font-size:0;line-height:0;width:226px;height:68px;margin:0 0 18px;padding:10px 14px;border:1px solid rgba\(234,216,186,\.14\);border-radius:16px;background:#fffdfa url\("\.\.\/images\/branding\/hotel-vastu-logo\.png"\) center\/198px auto no-repeat;box-shadow:0 12px 34px rgba\(0,0,0,\.14\)\}/g,
    '.footer-brand-logo{display:inline-flex;align-items:center;width:max-content;max-width:100%;margin-bottom:18px;padding:10px 14px;border:1px solid rgba(234,216,186,.14);border-radius:16px;background:#fffdfa;box-shadow:0 12px 34px rgba(0,0,0,.14)}.footer-brand-logo img{display:block;width:198px;height:auto;max-width:100%;object-fit:contain}');
fs.writeFileSync(layoutPath,layout);

const responsivePath=path.join(root,"css/responsive.css");
let responsive=fs.readFileSync(responsivePath,"utf8");
responsive=responsive.replace(
  '.brand{width:148px;height:46px}.site-header.is-scrolled .brand{width:142px}.footer h3{width:206px;height:62px;background-size:180px auto}',
  '.brand-logo img{width:148px}.site-header.is-scrolled .brand-logo img{width:142px}.footer-brand-logo img{width:180px}'
);
fs.writeFileSync(responsivePath,responsive);

console.log("Final polish applied to",htmlFiles.length,"HTML files.");
