export function blocageExtrait(data, aUnPolygone, plan = 'extrait') {
    if (!aUnPolygone) {
        return 'Cette parcelle n’a pas de géométrie.';
    }
    if (plan !== 'extrait') return '';
    const service = String(data?.slt_service_rendu || '').trim();
    if (service === 'Extrait de plan') {
        const lot = String(data?.txt_num_lotissement || '').trim();
        const lotissement = String(data?.txt_lotissement || '').trim();
        if (!lot || !lotissement) {
            return 'Le numéro de lot et le lotissement sont obligatoires pour un service Extrait de plan.';
        }
    }
    return '';
}

export function nomFichierExtrait(data, plan = 'extrait') {
    const base = String(data?.txt_nicad || data?.txt_num_dossier || 'parcelle').trim() || 'parcelle';
    const noms = {
        extrait: 'extrait-plan',
        cic: 'plan-cic',
        situation: 'plan-situation',
        morcellement: 'plan-morcellement',
    };
    return `${noms[plan] || 'plan'}-${base.replace(/[^\w.-]+/g, '-')}.pdf`;
}

export function anneauPrincipal(feature) {
    const geometry = feature?.geometry;
    if (!geometry) return [];
    let ring = [];
    if (geometry.type === 'Polygon') {
        ring = geometry.coordinates?.[0] || [];
    } else if (geometry.type === 'MultiPolygon') {
        ring = geometry.coordinates.reduce((meilleur, polygone) => {
            const candidat = polygone?.[0] || [];
            return candidat.length > meilleur.length ? candidat : meilleur;
        }, []);
    }
    return ring
        .map((point) => ({ lng: Number(point[0]), lat: Number(point[1]) }))
        .filter((point) => Number.isFinite(point.lng) && Number.isFinite(point.lat));
}

function court(valeur, max = 46) {
    const propre = valeurOuVide(valeur);
    if (!propre) return '';
    return propre.length > max ? `${propre.slice(0, max - 3)}...` : propre;
}

function largeurTexte(texte, taille, gras = false) {
    return String(texte ?? '').length * taille * (gras ? 0.56 : 0.54);
}

function dateDuJour() {
    const date = new Date();
    const deux = (nombre) => String(nombre).padStart(2, '0');
    return `${deux(date.getDate())}-${deux(date.getMonth() + 1)}-${date.getFullYear()}`;
}

export function formaterSuperficie(metresCarres) {
    const valeur = Number(metresCarres);
    if (!Number.isFinite(valeur) || valeur <= 0) return '—';
    const centiaresTotaux = Math.round(valeur);
    const ares = Math.floor(centiaresTotaux / 100);
    const centiares = centiaresTotaux % 100;
    return `${ares} a ${String(centiares).padStart(2, '0')} ca`;
}

function ouvrirAnneau(ring) {
    const points = ring.map((point) => ({ lng: point.lng, lat: point.lat }));
    if (points.length > 1) {
        const debut = points[0];
        const fin = points[points.length - 1];
        if (Math.abs(debut.lng - fin.lng) < 1e-10 && Math.abs(debut.lat - fin.lat) < 1e-10) {
            points.pop();
        }
    }
    return points;
}

function versLocal(point, origine) {
    const cos = Math.cos((origine.lat * Math.PI) / 180);
    return {
        x: (point.lng - origine.lng) * 111320 * cos,
        y: (point.lat - origine.lat) * 110540,
    };
}

function aireSignee(points) {
    let aire = 0;
    for (let i = 0; i < points.length; i += 1) {
        const suivant = points[(i + 1) % points.length];
        aire += points[i].x * suivant.y - suivant.x * points[i].y;
    }
    return aire / 2;
}

function sensHoraireDepuisLeNord(points) {
    const sens = aireSignee(points) > 0 ? [...points].reverse() : [...points];
    let depart = 0;
    sens.forEach((point, index) => {
        const meilleur = sens[depart];
        if (point.y > meilleur.y + 0.01 || (Math.abs(point.y - meilleur.y) <= 0.01 && point.x < meilleur.x)) {
            depart = index;
        }
    });
    return sens.slice(depart).concat(sens.slice(0, depart));
}

function presqueAlignes(precedent, courant, suivant) {
    const avantX = courant.x - precedent.x;
    const avantY = courant.y - precedent.y;
    const apresX = suivant.x - courant.x;
    const apresY = suivant.y - courant.y;
    const angle = Math.atan2(avantX * apresY - avantY * apresX, avantX * apresX + avantY * apresY);
    return Math.abs(angle) < (12 * Math.PI) / 180;
}

function coinsDuPolygone(points) {
    let courants = points;
    [0.4, 0.8, 1.5].some((seuil) => {
        const espaces = [];
        courants.forEach((point) => {
            const dernier = espaces[espaces.length - 1];
            if (!dernier || Math.hypot(point.x - dernier.x, point.y - dernier.y) >= seuil) {
                espaces.push(point);
            }
        });
        if (espaces.length > 2) {
            const debut = espaces[0];
            const fin = espaces[espaces.length - 1];
            if (Math.hypot(debut.x - fin.x, debut.y - fin.y) < seuil) espaces.pop();
        }
        courants = espaces.length >= 3 ? espaces : courants;
        return courants.length <= 20;
    });
    if (courants.length <= 20) {
        const coins = courants.filter((point, index) => {
            const precedent = courants[(index + courants.length - 1) % courants.length];
            const suivant = courants[(index + 1) % courants.length];
            return !presqueAlignes(precedent, point, suivant);
        });
        if (coins.length >= 3 && coins.length <= 20) return coins;
    }
    return courants.slice(0, 20);
}

function couperBord(dedans, dehors, limite, horizontal) {
    const partage = horizontal
        ? (limite - dedans[0]) / (dehors[0] - dedans[0] || 1)
        : (limite - dedans[1]) / (dehors[1] - dedans[1] || 1);
    return [
        dedans[0] + (dehors[0] - dedans[0]) * partage,
        dedans[1] + (dehors[1] - dedans[1]) * partage,
    ];
}

function rognerPolygone(points, cadre) {
    const tests = [
        (point) => point[0] >= cadre.x,
        (point) => point[0] <= cadre.x + cadre.w,
        (point) => point[1] >= cadre.y,
        (point) => point[1] <= cadre.y + cadre.h,
    ];
    const coupes = [
        (a, b) => couperBord(a, b, cadre.x, true),
        (a, b) => couperBord(a, b, cadre.x + cadre.w, true),
        (a, b) => couperBord(a, b, cadre.y, false),
        (a, b) => couperBord(a, b, cadre.y + cadre.h, false),
    ];
    return tests.reduce((courants, dedans, index) => {
        if (courants.length < 3) return [];
        const suivants = [];
        courants.forEach((point, position) => {
            const precedent = courants[(position + courants.length - 1) % courants.length];
            if (dedans(point)) {
                if (!dedans(precedent)) suivants.push(coupes[index](precedent, point));
                suivants.push(point);
            } else if (dedans(precedent)) {
                suivants.push(coupes[index](precedent, point));
            }
        });
        return suivants;
    }, points);
}

function dansRectangle(point, cadre, marge = 0) {
    return point[0] >= cadre.x + marge
        && point[0] <= cadre.x + cadre.w - marge
        && point[1] >= cadre.y + marge
        && point[1] <= cadre.y + cadre.h - marge;
}

function rognerSegment(a, b, cadre) {
    const xmin = cadre.x;
    const xmax = cadre.x + cadre.w;
    const ymin = cadre.y;
    const ymax = cadre.y + cadre.h;
    const dx = b[0] - a[0];
    const dy = b[1] - a[1];
    const p = [-dx, dx, -dy, dy];
    const q = [a[0] - xmin, xmax - a[0], a[1] - ymin, ymax - a[1]];
    let u1 = 0;
    let u2 = 1;
    for (let i = 0; i < 4; i += 1) {
        if (Math.abs(p[i]) < 1e-9) {
            if (q[i] < 0) return null;
        } else {
            const t = q[i] / p[i];
            if (p[i] < 0) u1 = Math.max(u1, t);
            else u2 = Math.min(u2, t);
            if (u1 > u2) return null;
        }
    }
    return [
        [a[0] + u1 * dx, a[1] + u1 * dy],
        [a[0] + u2 * dx, a[1] + u2 * dy],
    ];
}

function versPage(point, cadre, echelle, vue) {
    const centreX = (cadre.minX + cadre.maxX) / 2;
    const centreY = (cadre.minY + cadre.maxY) / 2;
    return [
        vue.x + vue.w / 2 + ((point.x - centreX) * 1000) / echelle,
        vue.y + vue.h / 2 - ((point.y - centreY) * 1000) / echelle,
    ];
}

function contient(point, anneau) {
    let dedans = false;
    for (let i = 0, j = anneau.length - 1; i < anneau.length; j = i, i += 1) {
        const croise = (anneau[i].y > point.y) !== (anneau[j].y > point.y)
            && point.x < ((anneau[j].x - anneau[i].x) * (point.y - anneau[i].y)) / (anneau[j].y - anneau[i].y) + anneau[i].x;
        if (croise) dedans = !dedans;
    }
    return dedans;
}

function longueurMetres(a, b) {
    const rayon = 6371000;
    const rad = Math.PI / 180;
    const dLat = (b.lat - a.lat) * rad;
    const dLng = (b.lng - a.lng) * rad;
    const h = Math.sin(dLat / 2) ** 2
        + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) ** 2;
    return 2 * rayon * Math.asin(Math.min(1, Math.sqrt(h)));
}

function normaleSortante(a, b) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const longueur = Math.hypot(dx, dy) || 1;
    return { x: -dy / longueur, y: dx / longueur, ux: dx / longueur, uy: dy / longueur };
}

function croisement(origine, direction, p, q) {
    const sx = q.x - p.x;
    const sy = q.y - p.y;
    const det = direction.x * sy - direction.y * sx;
    if (Math.abs(det) < 1e-8) return null;
    const qx = p.x - origine.x;
    const qy = p.y - origine.y;
    const t = (qx * sy - qy * sx) / det;
    const u = (qx * direction.y - qy * direction.x) / det;
    if (t < 1.6 || t > 45 || u < -0.04 || u > 1.04) return null;
    return { t, point: { x: origine.x + direction.x * t, y: origine.y + direction.y * t } };
}

function suiteAuSommet(sommet, ux, uy, parcelles) {
    let bout = null;
    let portee = 0.8;
    parcelles.forEach((parcelle) => {
        parcelle.points.forEach((p, index) => {
            const q = parcelle.points[(index + 1) % parcelle.points.length];
            const dx = q.x - p.x;
            const dy = q.y - p.y;
            const longueur = Math.hypot(dx, dy);
            if (longueur < 0.4) return;
            if (Math.abs((dx * ux + dy * uy) / longueur) < 0.96) return;
            const lateral = Math.abs((sommet.x - p.x) * (-dy / longueur) + (sommet.y - p.y) * (dx / longueur));
            if (lateral > 1.4) return;
            const progP = (p.x - sommet.x) * ux + (p.y - sommet.y) * uy;
            const progQ = (q.x - sommet.x) * ux + (q.y - sommet.y) * uy;
            const prog = Math.max(progP, progQ);
            if (prog <= portee || Math.min(progP, progQ) > 1.6) return;
            portee = prog;
            bout = progP > progQ ? { x: p.x, y: p.y } : { x: q.x, y: q.y };
        });
    });
    return bout;
}

function bordureDEnFace(a, b, sens, tous) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const etapes = Math.max(4, Math.round(Math.hypot(dx, dy) / 2));
    const impacts = [];
    for (let i = 1; i < etapes; i += 1) {
        const origine = { x: a.x + dx * (i / etapes), y: a.y + dy * (i / etapes) };
        let proche = null;
        tous.forEach((parcelle) => {
            parcelle.points.forEach((point, index) => {
                const suivant = parcelle.points[(index + 1) % parcelle.points.length];
                const hit = croisement(origine, sens, point, suivant);
                if (hit && (!proche || hit.t < proche.t)) proche = { ...hit, parcelle };
            });
        });
        if (proche) impacts.push(proche);
    }
    if (impacts.length < 2) return null;
    const compte = new Map();
    impacts.forEach((impact) => compte.set(impact.parcelle, (compte.get(impact.parcelle) || 0) + 1));
    let parcelle = null;
    let voix = 0;
    compte.forEach((nombre, item) => {
        if (nombre > voix) {
            voix = nombre;
            parcelle = item;
        }
    });
    const gardes = impacts.filter((impact) => impact.parcelle === parcelle);
    if (gardes.length < 2) return null;
    gardes.sort((gauche, droite) => (
        (gauche.point.x - a.x) * sens.ux + (gauche.point.y - a.y) * sens.uy
        - ((droite.point.x - a.x) * sens.ux + (droite.point.y - a.y) * sens.uy)
    ));
    const premier = gardes[0].point;
    const dernier = gardes[gardes.length - 1].point;
    return {
        parcelle,
        ecart: gardes.reduce((somme, impact) => somme + impact.t, 0) / gardes.length,
        milieu: { x: (premier.x + dernier.x) / 2, y: (premier.y + dernier.y) / 2 },
        debut: premier,
        fin: dernier,
    };
}

function bordSection(a, b, sens, lignes) {
    const longueur = Math.hypot(b.x - a.x, b.y - a.y);
    let meilleur = null;
    lignes.forEach((ligne) => {
        for (let i = 0; i < ligne.length - 1; i += 1) {
            const p = ligne[i];
            const q = ligne[i + 1];
            const ex = q.x - p.x;
            const ey = q.y - p.y;
            const el = Math.hypot(ex, ey);
            if (el < 1 || Math.abs((ex * sens.ux + ey * sens.uy) / el) < 0.92) continue;
            const milieu = { x: (p.x + q.x) / 2, y: (p.y + q.y) / 2 };
            const ecart = (milieu.x - a.x) * sens.x + (milieu.y - a.y) * sens.y;
            if (ecart < 2 || ecart > 40) continue;
            const proj = (point) => (point.x - a.x) * sens.ux + (point.y - a.y) * sens.uy;
            const lo = Math.max(0, Math.min(proj(p), proj(q)));
            const hi = Math.min(longueur, Math.max(proj(p), proj(q)));
            if (hi - lo < Math.min(4, longueur * 0.35)) continue;
            if (!meilleur || ecart < meilleur.ecart) {
                meilleur = { ecart, debut: p, fin: q, milieu };
            }
        }
    });
    return meilleur;
}

function analyserCotes(anneau, voisins, tous, lignesSection = []) {
    return anneau.map((a, index) => {
        const b = anneau[(index + 1) % anneau.length];
        const sens = normaleSortante(a, b);
        const longueur = Math.hypot(b.x - a.x, b.y - a.y);
        const etapes = Math.max(2, Math.round(longueur / 0.8));
        const compte = new Map();
        for (let etape = 0; etape <= etapes; etape += 1) {
            const t = etape / etapes;
            const voisin = voisins.find((item) => contient({
                x: a.x + (b.x - a.x) * t + sens.x * 1.3,
                y: a.y + (b.y - a.y) * t + sens.y * 1.3,
            }, item.points));
            if (voisin) compte.set(voisin, (compte.get(voisin) || 0) + 1);
        }
        let voisin = null;
        let voix = 0;
        compte.forEach((nombre, item) => {
            if (nombre > voix) {
                voix = nombre;
                voisin = item;
            }
        });
        const touche = voix / (etapes + 1) >= 0.35;
        const milieu = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        const face = touche ? null : (bordureDEnFace(a, b, sens, tous) || bordSection(a, b, sens, lignesSection));
        const avant = suiteAuSommet(a, -sens.ux, -sens.uy, tous);
        const apres = suiteAuSommet(b, sens.ux, sens.uy, tous);
        if (face?.parcelle) {
            const sensFace = normaleSortante(face.debut, face.fin);
            face.debut = suiteAuSommet(face.debut, -sensFace.ux, -sensFace.uy, tous) || face.debut;
            face.fin = suiteAuSommet(face.fin, sensFace.ux, sensFace.uy, tous) || face.fin;
        }
        return {
            a,
            b,
            milieu,
            longueur,
            nx: sens.x,
            ny: sens.y,
            voisin: touche ? voisin : null,
            rue: !touche,
            face,
            debut: avant || a,
            fin: apres || b,
        };
    }).filter((cote) => cote.longueur >= 0.4);
}

function segmentDansDisque(a, b, centre, rayon) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const fx = a.x - centre.x;
    const fy = a.y - centre.y;
    const aa = dx * dx + dy * dy;
    const bb = 2 * (fx * dx + fy * dy);
    const cc = fx * fx + fy * fy - rayon * rayon;
    const dedans = (point) => (point.x - centre.x) ** 2 + (point.y - centre.y) ** 2 <= rayon * rayon + 0.01;
    const bornes = [0, 1];
    if (aa > 1e-9) {
        const delta = bb * bb - 4 * aa * cc;
        if (delta >= 0) {
            const racine = Math.sqrt(delta);
            [-bb - racine, -bb + racine].forEach((valeur) => {
                const t = valeur / (2 * aa);
                if (t > 0 && t < 1) bornes.push(t);
            });
        }
    }
    bornes.sort((gauche, droite) => gauche - droite);
    const morceaux = [];
    for (let i = 0; i < bornes.length - 1; i += 1) {
        const debut = bornes[i];
        const fin = bornes[i + 1];
        if (fin - debut < 1e-4) continue;
        const milieu = { x: a.x + dx * ((debut + fin) / 2), y: a.y + dy * ((debut + fin) / 2) };
        if (!dedans(milieu)) continue;
        morceaux.push([
            { x: a.x + dx * debut, y: a.y + dy * debut },
            { x: a.x + dx * fin, y: a.y + dy * fin },
        ]);
    }
    return morceaux;
}

function libelleLot(valeur) {
    const chiffres = String(valeur ?? '').replace(/\D/g, '');
    if (!chiffres) return '';
    const nombre = Number.parseInt(chiffres, 10);
    return Number.isFinite(nombre) ? String(nombre) : '';
}

function centreVisible(points, centre, rayon) {
    const dansDisque = (point) => (point.x - centre.x) ** 2 + (point.y - centre.y) ** 2 <= rayon * rayon + 0.01;
    const barycentre = (liste) => liste.reduce((somme, point) => ({
        x: somme.x + point.x / liste.length,
        y: somme.y + point.y / liste.length,
    }), { x: 0, y: 0 });
    const milieu = barycentre(points);
    if (dansDisque(milieu) && contient(milieu, points)) return milieu;
    const interieurs = points.filter(dansDisque);
    if (!interieurs.length) return null;
    const candidat = barycentre(interieurs);
    if (dansDisque(candidat) && contient(candidat, points)) return candidat;
    const rapproche = {
        x: interieurs[0].x * 0.65 + milieu.x * 0.35,
        y: interieurs[0].y * 0.65 + milieu.y * 0.35,
    };
    return dansDisque(rapproche) && contient(rapproche, points) ? rapproche : null;
}

function nomRue(ecart) {
    if (!Number.isFinite(ecart) || ecart < 3 || ecart > 45) return 'Rue non dénommée';
    return `Rue de ${Math.round(ecart)} m`;
}

function mentionDossier(numero) {
    const propre = valeurOuVide(numero);
    return propre ? `DN° : ${propre}` : 'DN° :';
}

function nomSujet(infos) {
    const titre = valeurOuVide(infos.titre);
    if (!titre) return 'TNI';
    return /^(tf\b|t\.)/i.test(titre) ? titre : `TF ${titre}`;
}

function angleLisible(a, b) {
    let degres = (Math.atan2(b[1] - a[1], b[0] - a[0]) * 180) / Math.PI;
    if (degres > 90) degres -= 180;
    if (degres < -90) degres += 180;
    return degres;
}

function valeurOuVide(valeur) {
    return String(valeur ?? '').replace(/\s+/g, ' ').trim();
}

function pointille(valeur) {
    return valeurOuVide(valeur) || '............';
}

function nomLivre(code) {
    const propre = valeurOuVide(code).toUpperCase();
    if (!propre) return '';
    if (propre === 'NO') return 'NIANI OULI';
    return propre;
}

function formaterNicad(valeur) {
    const chiffres = String(valeur ?? '').replace(/\D/g, '');
    if (chiffres.length !== 16) return valeurOuVide(valeur);
    const tailles = [2, 1, 3, 2, 3, 5];
    let curseur = 0;
    return tailles.map((taille) => {
        const morceau = chiffres.slice(curseur, curseur + taille);
        curseur += taille;
        return morceau;
    }).join(' ');
}

export function echelleRetenue(valeur, choix, defaut) {
    const nombre = Number(valeur);
    return choix.includes(nombre) ? nombre : defaut;
}

function empriseDe(points) {
    return points.reduce((boite, point) => ({
        minX: Math.min(boite.minX, point.x),
        maxX: Math.max(boite.maxX, point.x),
        minY: Math.min(boite.minY, point.y),
        maxY: Math.max(boite.maxY, point.y),
    }), { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity });
}

function dansEmprise(point, boite, marge) {
    return point.x >= boite.minX - marge && point.x <= boite.maxX + marge
        && point.y >= boite.minY - marge && point.y <= boite.maxY + marge;
}

function fenetre(centre, cadre, echelle) {
    return {
        minX: centre.x - (cadre.w * echelle) / 2000,
        maxX: centre.x + (cadre.w * echelle) / 2000,
        minY: centre.y - (cadre.h * echelle) / 2000,
        maxY: centre.y + (cadre.h * echelle) / 2000,
    };
}

export function porteeSituation(echelle) {
    const retenue = Number(echelle) >= 2000 ? Number(echelle) : 2000;
    const demiX = (74 * retenue) / 2000;
    const demiY = (50 * retenue) / 2000;
    return Math.ceil(Math.hypot(demiX, demiY) + 40);
}

export function porteeCarte(echelle, cadre) {
    const retenue = Number(echelle) || 1000;
    const demiX = (cadre.w * retenue) / 2000;
    const demiY = (cadre.h * retenue) / 2000;
    return Math.ceil(Math.hypot(demiX, demiY) + 30);
}

function utm28(lng, lat) {
    const a = 6378137;
    const f = 1 / 298.257223563;
    const k0 = 0.9996;
    const e2 = f * (2 - f);
    const ep2 = e2 / (1 - e2);
    const lon0 = (-15 * Math.PI) / 180;
    const latR = (lat * Math.PI) / 180;
    const lonR = (lng * Math.PI) / 180;
    const sinus = Math.sin(latR);
    const cosinus = Math.cos(latR);
    const n = a / Math.sqrt(1 - e2 * sinus * sinus);
    const t = Math.tan(latR) ** 2;
    const c = ep2 * cosinus * cosinus;
    const grandA = cosinus * (lonR - lon0);
    const e4 = e2 * e2;
    const e6 = e4 * e2;
    const m = a * ((1 - e2 / 4 - (3 * e4) / 64 - (5 * e6) / 256) * latR
        - ((3 * e2) / 8 + (3 * e4) / 32 + (45 * e6) / 1024) * Math.sin(2 * latR)
        + ((15 * e4) / 256 + (45 * e6) / 1024) * Math.sin(4 * latR)
        - ((35 * e6) / 3072) * Math.sin(6 * latR));
    const est = k0 * n * (grandA + ((1 - t + c) * grandA ** 3) / 6
        + ((5 - 18 * t + t * t + 72 * c - 58 * ep2) * grandA ** 5) / 120) + 500000;
    const nord = k0 * (m + n * Math.tan(latR) * (grandA ** 2 / 2
        + ((5 - t + 9 * c + 4 * c * c) * grandA ** 4) / 24
        + ((61 - 58 * t + t * t + 600 * c - 330 * ep2) * grandA ** 6) / 720));
    return { x: est, y: nord };
}

function hachurer(points, ajouter, couleur, pas = 2.4) {
    if (points.length < 3) return;
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;
    points.forEach(([x, y]) => {
        minX = Math.min(minX, x);
        maxX = Math.max(maxX, x);
        minY = Math.min(minY, y);
        maxY = Math.max(maxY, y);
    });
    for (let base = minX - (maxY - minY); base < maxX; base += pas) {
        const croisements = [];
        for (let i = 0; i < points.length; i += 1) {
            const depart = points[i];
            const arrivee = points[(i + 1) % points.length];
            const dx = arrivee[0] - depart[0];
            const dy = arrivee[1] - depart[1];
            const denom = dy - dx;
            if (Math.abs(denom) < 1e-9) continue;
            const t = (depart[0] - base - depart[1]) / denom;
            if (t < -1e-6 || t > 1 + 1e-6) continue;
            croisements.push([depart[0] + t * dx, depart[1] + t * dy]);
        }
        croisements.sort((gauche, droite) => gauche[0] - droite[0]);
        for (let i = 0; i + 1 < croisements.length; i += 2) {
            ajouter({
                type: 'line',
                x1: croisements[i][0],
                y1: croisements[i][1],
                x2: croisements[i + 1][0],
                y2: croisements[i + 1][1],
                width: 0.12,
                color: couleur,
            });
        }
    }
}

export function commandesExtrait({ bureau, ville, infos, sujet, parcelles, sections, superficieM2, echelles, variante = 'extrait', signataire = '' }) {
    const anneau = ouvrirAnneau(sujet);
    if (anneau.length < 3) return null;
    const origine = anneau.reduce((somme, point) => ({
        lng: somme.lng + point.lng / anneau.length,
        lat: somme.lat + point.lat / anneau.length,
    }), { lng: 0, lat: 0 });
    const localiser = (ring) => ouvrirAnneau(ring).map((point) => ({
        ...versLocal(point, origine),
        lng: point.lng,
        lat: point.lat,
    }));
    const sujetLocal = sensHoraireDepuisLeNord(localiser(anneau));
    const emprise = empriseDe(sujetLocal);
    const autour = (parcelles || []).map((parcelle) => ({
        lot: valeurOuVide(parcelle.lot),
        parcelle: valeurOuVide(parcelle.parcelle),
        surface: Number(parcelle.surface) || 0,
        titre: valeurOuVide(parcelle.titre),
        touche: !!parcelle.touche,
        interieur: !!parcelle.interieur,
        points: localiser(parcelle.ring || []),
    })).filter((parcelle) => parcelle.points.length >= 3);
    const commandes = [];
    const ajouter = (commande) => commandes.push(commande);
    const textePage = (x, y, contenu, size = 2.5, weight = 'normal', anchor = 'start', rotation = 0) => {
        if (!String(contenu ?? '').trim()) return;
        ajouter({ type: 'text', x, y, text: contenu, size, weight, anchor, rotation });
    };
    const centreDe = (points) => points.reduce((somme, point) => ({
        x: somme.x + point.x / points.length,
        y: somme.y + point.y / points.length,
    }), { x: 0, y: 0 });
    const lignesSection = [];
    (sections || []).forEach((anneau) => {
        const points = localiser(anneau);
        for (let i = 0; i < points.length - 1; i += 1) {
            if (dansEmprise(points[i], emprise, 80) || dansEmprise(points[i + 1], emprise, 80)) {
                lignesSection.push([points[i], points[i + 1]]);
            }
        }
    });
    const coins = coinsDuPolygone(sujetLocal);
    const touches = autour.filter((parcelle) => parcelle.touche);
    const cotes = analyserCotes(coins, touches, autour, lignesSection);
    const coteNom = cotes.reduce((retenu, cote) => {
        const libre = !cote.voisin;
        const retenuLibre = !retenu.voisin;
        if (libre !== retenuLibre) return libre ? cote : retenu;
        if (cote.rue !== retenu.rue) return cote.rue ? retenu : cote;
        return cote.longueur > retenu.longueur ? cote : retenu;
    }, cotes[0]);

    const axeGauche = 46;
    const basCartouche = 49.2;
    textePage(axeGauche, 8, 'RÉPUBLIQUE DU SÉNÉGAL', 3.7, 'bold', 'middle');
    textePage(axeGauche, 13, 'Un Peuple – Un But – Une Foi', 2.2, 'normal', 'middle');
    ajouter({ type: 'line', x1: axeGauche - 14, y1: 15.2, x2: axeGauche + 14, y2: 15.2, width: 0.2 });
    textePage(axeGauche, 18.6, 'MINISTÈRE DE L\'ECONOMIE', 3.8, 'bold', 'middle');
    textePage(axeGauche, 23.2, 'DES FINANCES ET DU PLAN', 3.8, 'bold', 'middle');
    ajouter({ type: 'image', src: '/logo-dgid.png', x: axeGauche - 22, y: 26.2, w: 44, h: 11 });
    textePage(axeGauche, 42.2, `CENTRE DES SERVICES FISCAUX DE ${valeurOuVide(bureau).toUpperCase()}`, 2.4, 'bold', 'middle');
    textePage(axeGauche, basCartouche, 'BUREAU DU CADASTRE', 2.7, 'bold', 'middle');
    textePage(96, 9, valeurOuVide(infos.titre) ? `TITRE N° ${valeurOuVide(infos.titre)}` : 'TITRE N°', 6.2, 'bold', 'start');
    textePage(198, 10, 'LIVRE FONCIER DE', 4, 'bold', 'end');
    textePage(198, 16.5, nomLivre(infos.livre) || '............', 4.2, 'bold', 'end');

    const situation = { x: 12, y: 54, w: 74, h: 50 };
    const principal = { x: 16, y: 116, w: 178, h: 108 };
    ajouter({ type: 'rect', x: situation.x, y: situation.y, w: situation.w, h: situation.h, width: 0.3 });

    const echelleSituation = echelleRetenue(echelles?.situation, [2000, 5000, 10000], 2000);
    const echellePrincipale = echelleRetenue(echelles?.parcelle, [200, 500, 1000], 500);
    const centreEmprise = {
        x: (emprise.minX + emprise.maxX) / 2,
        y: (emprise.minY + emprise.maxY) / 2,
    };
    const boiteSituation = fenetre(centreEmprise, situation, echelleSituation);
    const boitePrincipale = fenetre(centreEmprise, principal, echellePrincipale);
    const pageSituation = (point) => versPage(point, boiteSituation, echelleSituation, situation);
    const pagePrincipale = (point) => versPage(point, boitePrincipale, echellePrincipale, principal);
    const lignePrincipale = (a, b, width) => {
        const segment = rognerSegment(a, b, principal);
        if (!segment) return;
        ajouter({ type: 'line', x1: segment[0][0], y1: segment[0][1], x2: segment[1][0], y2: segment[1][1], width });
    };
    const textePrincipal = (x, y, contenu, size, weight, anchor, rotation = 0) => {
        if (!dansRectangle([x, y], principal, 1)) return;
        textePage(x, y, contenu, size, weight, anchor, rotation);
    };
    const dehors = (point, nx, ny, metres) => pagePrincipale({
        x: point.x + nx * metres,
        y: point.y + ny * metres,
    });

    const ajouterSituation = (points, stroke, width) => {
        const rogne = rognerPolygone(points.map(pageSituation), situation);
        if (rogne.length >= 3) {
            ajouter({ type: 'polygon', points: rogne, fill: null, stroke, width });
        }
    };
    autour.forEach((parcelle) => {
        ajouterSituation(parcelle.points, [0, 0, 0], 0.15);
        const [x, y] = pageSituation(centreDe(parcelle.points));
        if (x > situation.x + 2 && x < situation.x + situation.w - 2 && y > situation.y + 2 && y < situation.y + situation.h - 2) {
            textePage(x, y, parcelle.lot, 1.5, 'normal', 'middle');
        }
    });
    ajouterSituation(sujetLocal, [0, 0, 0], 0.7);
    const centreSituation = pageSituation(centreDe(sujetLocal));
    textePage(centreSituation[0], centreSituation[1], valeurOuVide(infos.lot), 1.6, 'bold', 'middle');
    textePage(situation.x, situation.y + situation.h + 3.4, `PLAN DE SITUATION ECHELLE = 1 : ${echelleSituation}`, 1.7, 'normal', 'start');

    const xMilieu = 96;
    const xDroite = 154;
    const tailleLigne = 2.9;
    const ligneRequerant = `Requérant : ${valeurOuVide(infos.requerant)}`;
    const requerantLarge = largeurTexte(ligneRequerant, tailleLigne) > xDroite - xMilieu - 4;
    const lignesMilieu = [
        ['Région', infos.region],
        ['Département', infos.departement],
        ['Arrondissement', infos.arrondissement],
        ['Commune', infos.commune],
    ];
    const lignesDroite = [
        { text: `Réquisition n° ${pointille(infos.requisition)}`, weight: 'normal', size: tailleLigne },
        { text: `Titre Mère n° ${valeurOuVide(infos.titreMere) || 'TNI'}`, weight: 'normal', size: tailleLigne },
        { text: `Lot n°${valeurOuVide(infos.lot) || '............'}`, weight: 'normal', size: tailleLigne },
        { text: valeurOuVide(infos.lotissement) ? court(infos.lotissement, 22) : '', weight: 'normal', size: tailleLigne },
        { text: `NICAD ${formaterNicad(infos.nicad) || '............'}`, weight: 'bold', size: 3.2 },
    ];
    const rangs = requerantLarge ? lignesDroite.length + 1 : lignesDroite.length;
    const pas = (basCartouche - 20) / (rangs - 1);
    const yDe = (index) => 20 + index * pas;
    lignesMilieu.forEach(([libelle, valeur], index) => {
        textePage(xMilieu, yDe(index), `${libelle} : ${court(valeur, 16)}`, tailleLigne, 'normal', 'start');
    });
    lignesDroite.forEach((ligne, index) => {
        textePage(xDroite, yDe(index), ligne.text, ligne.size, ligne.weight, 'start');
    });
    const tailleRequerant = requerantLarge
        ? Math.max(1.8, Math.min(tailleLigne, tailleLigne * 100 / largeurTexte(ligneRequerant, tailleLigne)))
        : tailleLigne;
    textePage(xMilieu, basCartouche, ligneRequerant, tailleRequerant, 'normal', 'start');

    const nordX = situation.x + situation.w + 18;
    const nordY = situation.y + 6;
    textePage(nordX, nordY, 'NORD', 5, 'bold', 'middle');
    ajouter({
        type: 'polygon',
        points: [[nordX, nordY + 3.2], [nordX - 1.5, nordY + 6.4], [nordX + 1.5, nordY + 6.4]],
        fill: [0, 0, 0],
        stroke: [0, 0, 0],
        width: 0.15,
    });
    ajouter({ type: 'line', x1: nordX, y1: nordY + 6, x2: nordX, y2: nordY + 16, width: 0.4 });
    ajouter({ type: 'line', x1: nordX - 1.7, y1: nordY + 16, x2: nordX, y2: nordY + 13, width: 0.3 });
    ajouter({ type: 'line', x1: nordX + 1.7, y1: nordY + 16, x2: nordX, y2: nordY + 13, width: 0.3 });
    textePage(198, nordY, `Superficie : ${formaterSuperficie(superficieM2)}`, 4.2, 'normal', 'end');

    cotes.forEach((cote) => {
        if (!cote.voisin && !cote.rue) return;
        const depasse = Math.hypot(cote.debut.x - cote.a.x, cote.debut.y - cote.a.y) > 0.7
            || Math.hypot(cote.fin.x - cote.b.x, cote.fin.y - cote.b.y) > 0.7;
        if (depasse) {
            lignePrincipale(pagePrincipale(cote.debut), pagePrincipale(cote.fin), 0.35);
        }
        if (cote.face) {
            lignePrincipale(pagePrincipale(cote.face.debut), pagePrincipale(cote.face.fin), 0.35);
        }
        if (cote.rue) {
            const ecart = cote.face?.ecart;
            const angle = angleLisible(pagePrincipale(cote.a), pagePrincipale(cote.b));
            const rue = dehors(cote.milieu, cote.nx, cote.ny, ecart ? ecart / 2 : 6);
            textePrincipal(rue[0], rue[1], nomRue(ecart), 2.15, 'normal', 'middle', angle);
        }
    });
    const rayonMetres = (68 * echellePrincipale) / 1000;
    autour.forEach((parcelle) => {
        parcelle.points.forEach((point, index) => {
            const suivant = parcelle.points[(index + 1) % parcelle.points.length];
            segmentDansDisque(point, suivant, centreEmprise, rayonMetres).forEach(([debut, fin]) => {
                lignePrincipale(pagePrincipale(debut), pagePrincipale(fin), 0.3);
            });
        });
    });
    const sujetPage = rognerPolygone(sujetLocal.map(pagePrincipale), principal);
    if (sujetPage.length >= 3) {
        ajouter({
            type: 'polygon',
            points: sujetPage,
            fill: [255, 255, 255],
            stroke: variante === 'morcellement' ? [180, 0, 0] : [0, 0, 0],
            width: variante === 'morcellement' ? 0.7 : 0.55,
        });
    }
    if (variante === 'morcellement') {
        autour.filter((parcelle) => parcelle.interieur).forEach((parcelle) => {
            const page = rognerPolygone(parcelle.points.map(pagePrincipale), principal);
            if (page.length < 3) return;
            hachurer(page, ajouter, [80, 80, 80], 2.2);
            page.forEach((point, index) => {
                const suivant = page[(index + 1) % page.length];
                ajouter({ type: 'line', x1: point[0], y1: point[1], x2: suivant[0], y2: suivant[1], width: 0.25 });
            });
            const place = centreVisible(parcelle.points, centreEmprise, rayonMetres);
            if (!place) return;
            const [x, y] = pagePrincipale(place);
            textePrincipal(x, y, parcelle.lot || 'TNI', 2.15, 'normal', 'middle');
        });
    }
    autour.forEach((parcelle) => {
        if (parcelle.interieur) return;
        const place = centreVisible(parcelle.points, centreEmprise, rayonMetres);
        if (!place) return;
        const [x, y] = pagePrincipale(place);
        const lot = libelleLot(parcelle.lot);
        if (lot) {
            textePrincipal(x, y - 1.7, `Lot n° ${lot}`, 2.15, 'normal', 'middle');
            textePrincipal(x, y + 1.7, 'TNI', 2.15, 'normal', 'middle');
        } else {
            textePrincipal(x, y, 'TNI', 2.15, 'normal', 'middle');
        }
    });
    if (coteNom && !coteNom.voisin && !coteNom.rue) {
        const place = dehors(coteNom.milieu, coteNom.nx, coteNom.ny, 8);
        textePrincipal(place[0], place[1], nomSujet(infos), 2.4, 'bold', 'middle');
    }

    coins.forEach((point, index) => {
        const precedent = coins[(index + coins.length - 1) % coins.length];
        const suivant = coins[(index + 1) % coins.length];
        const avant = normaleSortante(precedent, point);
        const apres = normaleSortante(point, suivant);
        const nx = avant.x + apres.x;
        const ny = avant.y + apres.y;
        const norme = Math.hypot(nx, ny) || 1;
        const lettre = dehors(point, nx / norme, ny / norme, 2.2);
        textePrincipal(lettre[0], lettre[1], `B${index + 1}`, 2.2, 'bold', 'middle');
        const cote = cotes.find((item) => item.a === point && item.b === suivant);
        if (!cote) return;
        const mesure = dehors(cote.milieu, cote.nx, cote.ny, -2.2);
        const angle = angleLisible(pagePrincipale(cote.a), pagePrincipale(cote.b));
        textePrincipal(mesure[0], mesure[1], longueurMetres(point, suivant).toFixed(2), 2, 'normal', 'middle', angle);
    });

    textePage(105, 277, `ECHELLE = 1 : ${echellePrincipale}`, 2.4, 'bold', 'middle');
    if (variante === 'morcellement') {
        const bornes = coins.slice(0, 8).map((point, index) => {
            const projete = utm28(point.lng, point.lat);
            return [`B${index + 1}`, projete.x.toFixed(2), projete.y.toFixed(2)];
        });
        const tableX = 16;
        const tableY = 196;
        const tableH = 7 + bornes.length * 3.2;
        ajouter({ type: 'rect', x: tableX, y: tableY, w: 36, h: tableH, fill: [255, 255, 255], width: 0.25 });
        textePage(tableX + 2, tableY + 3.2, 'Pts', 1.8, 'bold', 'start');
        textePage(tableX + 9, tableY + 3.2, 'X', 1.8, 'bold', 'start');
        textePage(tableX + 20, tableY + 3.2, 'Y', 1.8, 'bold', 'start');
        bornes.forEach((ligne, index) => {
            const y = tableY + 7 + index * 3.2;
            textePage(tableX + 2, y, ligne[0], 1.7, 'normal', 'start');
            textePage(tableX + 9, y, ligne[1], 1.7, 'normal', 'start');
            textePage(tableX + 20, y, ligne[2], 1.7, 'normal', 'start');
        });
        const sousTable = tableY + tableH + 4;
        textePage(16, sousTable, 'Système de coordonnées : UTM 28N', 2, 'normal', 'start');
        textePage(16, sousTable + 4, 'Bornage effectué le ............', 2, 'normal', 'start');
        textePage(16, sousTable + 8, 'Par ............ · Ingénieur Géomètre', 2, 'normal', 'start');
        textePage(16, 252, 'Extrait certifié conforme', 2.4, 'bold', 'start');
        textePage(16, 258, '............, le ............', 2.2, 'normal', 'start');
        textePage(16, 264, 'Le chef du bureau', 2.2, 'normal', 'start');
        textePage(120, 252, `PLAN DU TITRE N° ${valeurOuVide(infos.titre) || '............'}`, 2.3, 'bold', 'start');
        textePage(120, 258, '............, le ............', 2.2, 'normal', 'start');
        textePage(120, 264, 'Le conservateur de la propriété', 2.2, 'normal', 'start');
        textePage(120, 268, 'et des droits fonciers', 2.2, 'normal', 'start');
    } else {
        textePage(16, 248, 'Extrait certifié conforme', 2.6, 'bold', 'start');
        textePage(16, 254, `${valeurOuVide(ville)}, le ${dateDuJour()}`, 2.5, 'normal', 'start');
        textePage(16, 262, 'Le chef du Bureau', 2.5, 'normal', 'start');
        textePage(16, 274, valeurOuVide(signataire), 2.5, 'normal', 'start');
        textePage(105, 288, mentionDossier(infos.dossier), 2.5, 'bold', 'middle');
    }
    ajouter({ type: 'rect', x: 10.6, y: 52.6, w: 190.8, h: 228.8, width: 0.35 });
    ajouter({ type: 'rect', x: 12, y: 54, w: 188, h: 226, width: 0.2 });

    return commandes;
}

export function commandesCarte({ type, bureau, infos, sujet, parcelles, superficieM2, echelle }) {
    const anneau = ouvrirAnneau(sujet);
    if (anneau.length < 3) return null;
    const origine = anneau.reduce((somme, point) => ({
        lng: somme.lng + point.lng / anneau.length,
        lat: somme.lat + point.lat / anneau.length,
    }), { lng: 0, lat: 0 });
    const localiser = (ring) => ouvrirAnneau(ring).map((point) => ({
        ...versLocal(point, origine),
        lng: point.lng,
        lat: point.lat,
    }));
    const sujetLocal = sensHoraireDepuisLeNord(localiser(anneau));
    const emprise = empriseDe(sujetLocal);
    const autour = (parcelles || []).map((parcelle) => ({
        lot: valeurOuVide(parcelle.lot),
        parcelle: valeurOuVide(parcelle.parcelle),
        surface: Number(parcelle.surface) || 0,
        touche: !!parcelle.touche,
        points: localiser(parcelle.ring || []),
    })).filter((parcelle) => parcelle.points.length >= 3);
    const commandes = [];
    const ajouter = (commande) => commandes.push(commande);
    const textePage = (x, y, contenu, size = 2.5, weight = 'normal', anchor = 'start', rotation = 0) => {
        if (!String(contenu ?? '').trim()) return;
        ajouter({ type: 'text', x, y, text: contenu, size, weight, anchor, rotation });
    };
    const axe = 42;
    textePage(axe, 8, 'RÉPUBLIQUE DU SÉNÉGAL', 3.2, 'bold', 'middle');
    textePage(axe, 12, 'Un Peuple – Un But – Une Foi', 1.9, 'normal', 'middle');
    ajouter({ type: 'line', x1: 28, y1: 14, x2: 56, y2: 14, width: 0.2 });
    textePage(axe, 17, 'MINISTÈRE DE L\'ECONOMIE', 3, 'bold', 'middle');
    textePage(axe, 21, 'DES FINANCES ET DU PLAN', 3, 'bold', 'middle');
    ajouter({ type: 'image', src: '/logo-dgid.png', x: 22, y: 23, w: 40, h: 9 });
    textePage(axe, 35, `CENTRE DES SERVICES FISCAUX DE ${valeurOuVide(bureau).toUpperCase()}`, 2, 'bold', 'middle');
    textePage(axe, 39, 'BUREAU DU CADASTRE', 2.2, 'bold', 'middle');

    const situation = type === 'situation';
    const cadre = situation
        ? { x: 12, y: 56, w: 186, h: 196 }
        : { x: 12, y: 50, w: 186, h: 230 };
    const echelleRetenueCarte = echelleRetenue(echelle, [500, 1000, 2000], 1000);
    const centreEmprise = {
        x: (emprise.minX + emprise.maxX) / 2,
        y: (emprise.minY + emprise.maxY) / 2,
    };
    const boite = fenetre(centreEmprise, cadre, echelleRetenueCarte);
    const page = (point) => versPage(point, boite, echelleRetenueCarte, cadre);
    const ligne = (a, b, width, color) => {
        const segment = rognerSegment(a, b, cadre);
        if (!segment) return;
        ajouter({ type: 'line', x1: segment[0][0], y1: segment[0][1], x2: segment[1][0], y2: segment[1][1], width, color });
    };
    const texteCadre = (x, y, contenu, size, weight = 'normal') => {
        if (!dansRectangle([x, y], cadre, 1)) return;
        textePage(x, y, contenu, size, weight, 'middle');
    };

    if (situation) {
        textePage(105, 12, 'PLAN DE SITUATION', 5, 'bold', 'middle');
        textePage(78, 20, `TF n° : ${valeurOuVide(infos.titre) || '............'}`, 3, 'bold', 'start');
        textePage(78, 26, `Lot n° : ${valeurOuVide(infos.lot) || '............'}`, 3, 'normal', 'start');
        textePage(78, 32, `Requérant : ${court(infos.requerant, 42) || '............'}`, 2.6, 'normal', 'start');
        ajouter({ type: 'rect', x: 150, y: 8, w: 48, h: 28, width: 0.3 });
        [
            ['Région', infos.region],
            ['Département', infos.departement],
            ['Arrond. / Com', infos.commune],
        ].forEach(([libelle, valeur], index) => {
            textePage(152, 13 + index * 6, `${libelle} : ${court(valeur, 14)}`, 2.1, 'normal', 'start');
        });
        textePage(16, 48, 'Référence : ............', 2.4, 'normal', 'start');
        textePage(198, 48, `Superficie : ${formaterSuperficie(superficieM2)}`, 2.8, 'normal', 'end');
    } else {
        textePage(78, 12, 'PLAN POUR CIC N° ............', 4.2, 'bold', 'start');
        textePage(78, 18, `Titre foncier ${valeurOuVide(infos.titre) || '............'}  Lot ${valeurOuVide(infos.lot) || '............'}`, 3, 'bold', 'start');
        textePage(78, 24, `Superficie = ${formaterSuperficie(superficieM2)}`, 2.8, 'normal', 'start');
        ajouter({ type: 'rect', x: 150, y: 8, w: 48, h: 30, width: 0.3 });
        [
            ['Région', infos.region],
            ['Département', infos.departement],
            ['Arrondissement', infos.arrondissement],
            ['Commune', infos.commune],
        ].forEach(([libelle, valeur], index) => {
            textePage(152, 13 + index * 6, `${libelle} : ${court(valeur, 12)}`, 2, 'normal', 'start');
        });
        textePage(198, 44, `NICAD : ${formaterNicad(infos.nicad) || '............'}`, 2.8, 'bold', 'end');
    }
    textePage(105, cadre.y - 4, 'NORD', 3.4, 'bold', 'middle');

    autour.forEach((parcelle) => {
        parcelle.points.forEach((point, index) => {
            const suivant = parcelle.points[(index + 1) % parcelle.points.length];
            ligne(page(point), page(suivant), 0.25);
        });
        const milieu = parcelle.points.reduce((somme, point) => ({
            x: somme.x + point.x / parcelle.points.length,
            y: somme.y + point.y / parcelle.points.length,
        }), { x: 0, y: 0 });
        if (!contient(milieu, parcelle.points)) return;
        const [x, y] = page(milieu);
        const lot = parcelle.lot || parcelle.parcelle;
        texteCadre(x, y - (situation ? 1.6 : 0), lot, 2.1, 'bold');
        if (situation && parcelle.surface > 0) texteCadre(x, y + 1.6, `${parcelle.surface} m²`, 1.7);
        if (situation && parcelle.parcelle && parcelle.lot) texteCadre(x, y + 4, parcelle.parcelle, 1.6);
    });

    const sujetPage = rognerPolygone(sujetLocal.map(page), cadre);
    if (sujetPage.length >= 3) {
        if (situation) hachurer(sujetPage, ajouter, [180, 0, 0], 2.2);
        ajouter({
            type: 'polygon',
            points: sujetPage,
            fill: situation ? null : [255, 255, 255],
            stroke: situation ? [180, 0, 0] : [0, 0, 0],
            width: situation ? 0.7 : 0.9,
        });
        const milieu = sujetLocal.reduce((somme, point) => ({
            x: somme.x + point.x / sujetLocal.length,
            y: somme.y + point.y / sujetLocal.length,
        }), { x: 0, y: 0 });
        const [x, y] = page(milieu);
        texteCadre(x, y, valeurOuVide(infos.lot), 2.6, 'bold');
    }

    if (situation) {
        const coins = coinsDuPolygone(sujetLocal);
        const cotes = analyserCotes(coins, autour.filter((parcelle) => parcelle.touche), autour, []);
        cotes.forEach((cote) => {
            if (!cote.rue) return;
            const angle = angleLisible(page(cote.a), page(cote.b));
            const place = page({
                x: cote.milieu.x + cote.nx * (cote.face?.ecart ? cote.face.ecart / 2 : 6),
                y: cote.milieu.y + cote.ny * (cote.face?.ecart ? cote.face.ecart / 2 : 6),
            });
            if (!dansRectangle(place, cadre, 1)) return;
            textePage(place[0], place[1], nomRue(cote.face?.ecart).toUpperCase(), 2, 'normal', 'middle', angle);
        });
        coins.forEach((point, index) => {
            const [x, y] = page(point);
            texteCadre(x, y, `B${index + 1}`, 1.8, 'bold');
        });
        const bornes = coins.slice(0, 8).map((point, index) => {
            const projete = utm28(point.lng, point.lat);
            return [`B${index + 1}`, projete.x.toFixed(2), projete.y.toFixed(2)];
        });
        const tableY = cadre.y + cadre.h + 3;
        ajouter({ type: 'rect', x: 12, y: tableY, w: 70, h: 6 + bornes.length * 3, fill: [255, 255, 255], width: 0.25 });
        bornes.forEach((ligne, index) => {
            textePage(16, tableY + 3 + index * 3, `${ligne[0]}   ${ligne[1]}   ${ligne[2]}`, 1.7, 'normal', 'start');
        });
        textePage(90, tableY + 4, 'Limite du lot', 2.2, 'normal', 'start');
        ajouter({ type: 'line', x1: 118, y1: tableY + 4, x2: 136, y2: tableY + 4, width: 0.6, color: [180, 0, 0] });
        textePage(90, tableY + 9, 'Limite de l’aménagement', 2.2, 'normal', 'start');
        ajouter({ type: 'line', x1: 140, y1: tableY + 9, x2: 158, y2: tableY + 9, width: 0.35 });
        textePage(16, 278, 'Le Géomètre', 2.6, 'normal', 'start');
        textePage(150, 278, 'Le Chef du bureau', 2.6, 'normal', 'start');
    }

    textePage(situation ? 105 : 16, 288, `Echelle = 1/${echelleRetenueCarte}`, 2.6, 'bold', situation ? 'middle' : 'start');
    ajouter({ type: 'rect', x: cadre.x, y: cadre.y, w: cadre.w, h: cadre.h, width: 0.35 });
    return commandes;
}

export async function telechargerExtrait(commandes, fichier) {
    const { jsPDF } = await import('jspdf');
    const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const images = new Map();
    for (const commande of commandes) {
        if (commande.type !== 'image' || images.has(commande.src)) continue;
        images.set(commande.src, await chargerImage(commande.src));
    }
    commandes.forEach((commande) => {
        if (commande.type === 'image') {
            const source = images.get(commande.src);
            if (source) doc.addImage(source, source.includes('image/png') ? 'PNG' : 'JPEG', commande.x, commande.y, commande.w, commande.h);
            return;
        }
        if (commande.type === 'rect') {
            const trait = commande.color || [0, 0, 0];
            doc.setDrawColor(...trait);
            doc.setLineWidth(commande.width || 0.25);
            if (commande.fill) {
                doc.setFillColor(...commande.fill);
                doc.rect(commande.x, commande.y, commande.w, commande.h, 'FD');
            } else {
                doc.rect(commande.x, commande.y, commande.w, commande.h);
            }
            return;
        }
        if (commande.type === 'line') {
            const trait = commande.color || [0, 0, 0];
            doc.setDrawColor(...trait);
            doc.setLineWidth(commande.width || 0.2);
            doc.line(commande.x1, commande.y1, commande.x2, commande.y2);
            return;
        }
        if (commande.type === 'polygon' && commande.points.length > 1) {
            const trait = commande.stroke || [0, 0, 0];
            doc.setDrawColor(...trait);
            doc.setLineWidth(commande.width || 0.25);
            const [depart, ...suite] = commande.points;
            const relatifs = [];
            let precedent = depart;
            suite.forEach((point) => {
                relatifs.push([point[0] - precedent[0], point[1] - precedent[1]]);
                precedent = point;
            });
            if (commande.fill) doc.setFillColor(...commande.fill);
            doc.lines(relatifs, depart[0], depart[1], [1, 1], commande.fill ? 'FD' : 'S', true);
            return;
        }
        if (commande.type === 'text') {
            const encre = commande.color || [0, 0, 0];
            doc.setTextColor(...encre);
            doc.setFont('helvetica', commande.weight === 'bold' ? 'bold' : 'normal');
            doc.setFontSize((commande.size || 2.5) * 2.83465);
            doc.text(String(commande.text ?? ''), commande.x, commande.y, {
                align: commande.anchor === 'middle' ? 'center' : commande.anchor === 'end' ? 'right' : 'left',
                baseline: 'middle',
                angle: -(commande.rotation || 0),
            });
        }
    });
    doc.save(fichier);
}

async function chargerImage(src) {
    if (!src || typeof fetch !== 'function') return null;
    try {
        const reponse = await fetch(src);
        if (!reponse.ok) return null;
        const blob = await reponse.blob();
        return await new Promise((resolve) => {
            const lecteur = new FileReader();
            lecteur.onload = () => resolve(lecteur.result);
            lecteur.onerror = () => resolve(null);
            lecteur.readAsDataURL(blob);
        });
    } catch (e) {
        return null;
    }
}
