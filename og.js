const sharp = require('sharp');
const SRC = '/mnt/c/Projetos/Marina Site/conteudo/imagens/';
const DST = '/mnt/c/Projetos/Marina Site/conteudo/imagens/og-capa.jpg';
const svg = `<svg width="1200" height="630" xmlns="http://www.w3.org/2000/svg">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="#2B0530"/><stop offset="1" stop-color="#5A0B57"/>
</linearGradient></defs>
<rect width="1200" height="630" fill="url(#g)"/>
<circle cx="980" cy="315" r="260" fill="#FF51C9" opacity="0.25"/>
<text x="80" y="170" font-family="Montserrat" font-weight="bold" font-size="44" letter-spacing="8" fill="#FF51C9">DESAFIO</text>
<text x="76" y="300" font-family="Anton" font-size="130" fill="#FFFFFF">PILATES</text>
<text x="76" y="430" font-family="Anton" font-size="130" fill="#FFFFFF">EM CASA</text>
<text x="80" y="510" font-family="Montserrat" font-size="36" fill="#F2D3EA">20 min por dia. Sem sair de casa.</text>
</svg>`;
(async () => {
  const bg = await sharp(Buffer.from(svg)).jpeg({ quality: 90 }).toBuffer();
  const foto = await sharp(SRC + 'Nath-com-fundo.png').resize(560, 560, { fit: 'cover' }).toBuffer();
  await sharp(bg).composite([{ input: foto, left: 620, top: 35 }]).jpeg({ quality: 85 }).toFile(DST);
  console.log('ok', (await sharp(DST).metadata().then((m) => m.width + 'x' + m.height)));
})();
