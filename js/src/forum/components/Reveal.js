import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import { call, errorText, rarityName, reducedMotion, setStatus, status, t, until } from '../util';
import confetti from '../confetti';
import { loadStyles } from '../styles';

/**
 * Opening packs, full screen over the forum. The pack shakes while the
 * server decides what is in it (the browser only asks), tears open, and
 * the stickers land face down; each flips when tapped (or all at once),
 * rare ones shine. Rarer cards glow through their backs, for suspense.
 */
export function openPacks() {
  if (document.querySelector('.StickerReveal')) return;
  const last = document.activeElement;
  const host = document.createElement('div');
  document.body.appendChild(host);
  document.body.classList.add('StickerReveal-open');

  const close = () => {
    m.mount(host, null);
    host.remove();
    document.body.classList.remove('StickerReveal-open');
    document.removeEventListener('keydown', onKey);
    last?.focus?.();
    // Whatever page is open shows the new stickers.
    window.dispatchEvent(new CustomEvent('sticker-book:changed'));
    m.redraw();
  };
  const onKey = (e) => {
    if (e.key === 'Escape') close();
  };
  document.addEventListener('keydown', onKey);

  loadStyles().then(() => m.mount(host, { view: () => <Reveal close={close} /> }));
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Wait for the art (up to a second and a half), so a card never flips to an empty face. */
function preload(cards) {
  const loads = cards.map(
    (card) =>
      new Promise((resolve) => {
        const img = new Image();
        img.onload = img.onerror = resolve;
        img.src = card.full;
      })
  );
  return Promise.race([Promise.all(loads), wait(1500)]);
}

class Reveal {
  oninit(vnode) {
    this.close = vnode.attrs.close;
    this.stage = 'pack'; // pack → opening → cards
    this.result = null;
    this.flipped = [];
    this.daily = !!status()?.daily;
  }

  oncreate(vnode) {
    vnode.dom.querySelector('.StickerPack')?.focus();
  }

  view() {
    const s = status() || {};
    return (
      <div className={`StickerReveal StickerReveal--${this.stage}`} role="dialog" aria-modal="true" aria-label={t('reveal.label')}>
        <div className="StickerReveal-backdrop" onclick={() => this.stage !== 'opening' && this.close()} />
        <button className="StickerReveal-close" aria-label={app.translator.trans('core.lib.modal.close_button')} onclick={this.close}>
          <Icon name="fas fa-xmark" />
        </button>
        <div className="StickerReveal-stage">{this.stage === 'cards' ? this.cards() : this.pack(s)}</div>
      </div>
    );
  }

  pack(s) {
    const left = s.packs || 0;
    if (!left && this.stage === 'pack') {
      return (
        <div className="StickerReveal-empty">
          <div className="StickerPack StickerPack--empty" aria-hidden="true">
            <span className="StickerPack-body">
              <Icon name="fas fa-moon" />
            </span>
          </div>
          <h2>{t('reveal.none_title')}</h2>
          <p>{t('reveal.none_text', { time: until(s.nextDaily) })}</p>
          <Button className="Button Button--primary" onclick={() => this.toAlbum()}>
            {t('reveal.see_album')}
          </Button>
        </div>
      );
    }

    return (
      <div className="StickerReveal-packView">
        <p className="StickerReveal-kicker">{this.daily ? t('reveal.daily') : t('reveal.earned')}</p>
        <button
          className={`StickerPack ${this.stage === 'opening' ? 'is-opening' : ''} ${this.torn ? 'is-torn' : ''}`}
          onclick={() => this.open()}
          disabled={this.stage === 'opening'}
          aria-label={t('reveal.tap')}
        >
          <span className="StickerPack-top" />
          <span className="StickerPack-body">
            <span className="StickerPack-shine" />
            <span className="StickerPack-logo">
              <Icon name="fas fa-note-sticky" />
            </span>
            <span className="StickerPack-name">{app.forum.attribute('title')}</span>
            <span className="StickerPack-label">{t('reveal.pack_label')}</span>
            <span className="StickerPack-count">{t('reveal.pack_count', { count: s.packSize || 5 })}</span>
          </span>
        </button>
        <p className="StickerReveal-hint">{this.stage === 'opening' ? t('reveal.opening') : t('reveal.tap')}</p>
        {this.error ? <p className="StickerReveal-error">{this.error}</p> : null}
        {left > 1 ? <p className="StickerReveal-left">{t('reveal.left', { count: left })}</p> : null}
      </div>
    );
  }

  cards() {
    const { cards, completed } = this.result;
    const done = this.flipped.every(Boolean);
    const left = status()?.packs || 0;
    const fresh = cards.filter((c) => c.new).length;

    return (
      <div className="StickerReveal-cardsView">
        <div className="StickerReveal-cards" style={{ '--n': cards.length }}>
          {cards.map((card, i) => (
            <button
              key={i}
              className={`StickerCard StickerCard--r${card.rarity} ${this.flipped[i] ? 'is-flipped' : ''}`}
              style={{ '--i': i }}
              onclick={(e) => this.flip(i, e.currentTarget)}
              aria-label={this.flipped[i] ? `${card.name}, ${rarityName(card.rarity)}` : t('reveal.flip')}
              aria-pressed={this.flipped[i] ? 'true' : 'false'}
            >
              <span className="StickerCard-inner">
                <span className="StickerCard-back">
                  <span className="StickerCard-backMark">
                    <Icon name="fas fa-star" />
                  </span>
                </span>
                <span className="StickerCard-front">
                  <span className="StickerCard-art">
                    <img src={card.full} alt="" draggable="false" />
                  </span>
                  <span className="StickerCard-name">{card.name}</span>
                  <span className="StickerCard-rarity">{rarityName(card.rarity)}</span>
                  {card.new ? (
                    <span className="StickerCard-new">{t('reveal.new')}</span>
                  ) : card.count > 1 ? (
                    <span className="StickerCard-dupe">×{card.count}</span>
                  ) : null}
                  {card.rarity >= 2 ? <span className="StickerCard-holo" /> : null}
                </span>
              </span>
            </button>
          ))}
        </div>

        {done && completed.length ? (
          <div className="StickerReveal-complete">
            <Icon name="fas fa-trophy" /> {t('reveal.completed', { set: completed.map((s) => s.name).join(', ') })}
          </div>
        ) : null}

        <div className="StickerReveal-actions">
          {!done ? (
            <Button className="Button StickerReveal-all" icon="fas fa-wand-magic-sparkles" onclick={() => this.flipAll()}>
              {t('reveal.flip_all')}
            </Button>
          ) : (
            [
              <p className="StickerReveal-summary">{fresh ? t('reveal.summary_new', { count: fresh }) : t('reveal.summary_none')}</p>,
              <div className="StickerReveal-buttons">
                {left ? (
                  <Button className="Button Button--primary" icon="fas fa-box-open" onclick={() => this.again()}>
                    {t('reveal.next', { count: left })}
                  </Button>
                ) : null}
                <Button className={left ? 'Button' : 'Button Button--primary'} icon="fas fa-book-open" onclick={() => this.toAlbum()}>
                  {t('reveal.see_album')}
                </Button>
              </div>,
            ]
          )}
        </div>
      </div>
    );
  }

  async open() {
    if (this.stage !== 'pack') return;
    this.stage = 'opening';
    this.error = null;
    const started = Date.now();

    let result;
    try {
      // Quiet: an alert would sit under the reveal, so the reason shows on the stage.
      result = await call('POST', '/open', undefined, true);
    } catch (e) {
      this.error = errorText(e);
      this.stage = 'pack';
      m.redraw();
      return;
    }
    setStatus(result.status);
    await Promise.all([preload(result.cards), wait(Math.max(0, (reducedMotion() ? 0 : 750) - (Date.now() - started)))]);

    this.torn = true;
    m.redraw();
    await wait(reducedMotion() ? 0 : 420);

    this.result = result;
    this.flipped = result.cards.map(() => false);
    this.torn = false;
    this.stage = 'cards';
    m.redraw();
  }

  flip(i, el) {
    if (this.flipped[i]) return;
    this.flipped[i] = true;
    const card = this.result.cards[i];
    if (card.rarity === 3) setTimeout(() => confetti(el, 70), reducedMotion() ? 0 : 350);
    if (this.flipped.every(Boolean) && this.result.completed.length) setTimeout(() => confetti(null, 90), 700);
  }

  async flipAll() {
    const buttons = document.querySelectorAll('.StickerReveal .StickerCard');
    for (let i = 0; i < this.flipped.length; i++) {
      if (this.flipped[i]) continue;
      this.flip(i, buttons[i]);
      m.redraw();
      await wait(reducedMotion() ? 0 : 260);
    }
  }

  again() {
    this.daily = !!status()?.daily;
    this.result = null;
    this.stage = 'pack';
    // Straight into the next one: the member already chose to open it.
    this.open();
  }

  toAlbum() {
    this.close();
    if (m.route.get() !== app.route('stickers')) m.route.set(app.route('stickers'));
  }
}
