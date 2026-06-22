/* Auto Business — visual canvas (Module 2, admin-only).
 *
 * Wraps Drawflow: a palette adds Trigger / Logic / Action / Transform nodes,
 * the user wires output ports to input ports, and Save serializes the layout +
 * logic into the engine's graph shape and POSTs it (CSRF-protected) to
 * /api/workflow/save.
 *
 * Engine graph shape produced here:
 *   { nodes:       [ {id, name, type, data}, ... ],
 *     connections: [ {from, fromHandle, to, toHandle}, ... ] }
 */
(function () {
  'use strict';

  const editor = new Drawflow(document.getElementById('canvas'));
  editor.reroute = true;
  editor.start();

  // Per-type port layout. If/Else exposes two output handles (true/false);
  // everything else has a single "output". Triggers have no input.
  const TYPES = {
    webhook:   { title: 'Webhook Trigger', inputs: 0, outputs: ['output'],          data: {} },
    cron:      { title: 'Cron Trigger',    inputs: 0, outputs: ['output'],          data: {} },
    if:        { title: 'If / Else',       inputs: 1, outputs: ['true', 'false'],   data: { left: '', operator: '==', right: '' } },
    transform: { title: 'Transform',       inputs: 1, outputs: ['output'],          data: { steps: [] } },
    http:      { title: 'HTTP Request',    inputs: 1, outputs: ['output'],          data: { method: 'GET', url: '', headers: {}, body: null } },
  };

  let counter = 0;

  function addNode(type, posX, posY) {
    const spec = TYPES[type];
    if (!spec) return;
    counter += 1;
    const name = type + '_' + counter; // unique, used for {{ Nodes.<name>.output }}

    // Node body: editable name + a JSON config textarea (compact, fully general;
    // per-type forms can be layered on later without changing the saved shape).
    const html = `
      <div class="px-2 py-2 space-y-1">
        <div class="title">${spec.title}</div>
        <input df-name value="${name}" class="w-full text-xs px-1 py-0.5 rounded text-black" />
        <textarea df-config rows="3"
          class="w-full text-xs px-1 py-0.5 rounded text-black font-mono"
          >${JSON.stringify(spec.data, null, 0)}</textarea>
      </div>`;

    editor.addNode(
      type,                 // Drawflow "name" — we reuse it as the node TYPE
      spec.inputs,
      spec.outputs.length,
      posX, posY,
      type,                 // CSS class
      { type: type, name: name, config: spec.data }, // df data
      html
    );
  }

  // Palette buttons.
  document.querySelectorAll('.palette').forEach((btn) => {
    btn.addEventListener('click', () => addNode(btn.dataset.add, 150 + Math.random() * 300, 80 + Math.random() * 300));
  });

  // Convert Drawflow's export into the engine graph shape.
  function toEngineGraph() {
    const exported = editor.export();
    const home = (exported.drawflow.Home && exported.drawflow.Home.data) || {};
    const nodes = [];
    const connections = [];

    Object.values(home).forEach((n) => {
      const type = n.name;
      const spec = TYPES[type] || { outputs: ['output'] };

      // Pull edited name + config out of the node's DOM-bound fields.
      const df = n.data || {};
      let config = df.config || {};
      // df-config textarea (if present) overrides with admin-edited JSON.
      if (df.config_text) {
        try { config = JSON.parse(df.config_text); } catch (e) { /* keep prior */ }
      }
      const name = df.name || (type + '_' + n.id);

      nodes.push({ id: String(n.id), name: name, type: type, data: config });

      // Map each output port index to its handle name (true/false for If/Else).
      Object.keys(n.outputs || {}).forEach((outKey) => {
        const idx = parseInt(outKey.split('_')[1], 10) - 1;
        const handle = spec.outputs[idx] || 'output';
        (n.outputs[outKey].connections || []).forEach((c) => {
          connections.push({ from: String(n.id), fromHandle: handle, to: String(c.node), toHandle: 'input' });
        });
      });
    });

    return { nodes: nodes, connections: connections };
  }

  // Keep df data in sync with the in-node inputs before exporting.
  function syncNodeFields() {
    document.querySelectorAll('.drawflow-node').forEach((el) => {
      const id = el.id.replace('node-', '');
      const nameEl = el.querySelector('[df-name]');
      const cfgEl = el.querySelector('[df-config]');
      const node = editor.getNodeFromId(id);
      if (!node) return;
      const data = node.data || {};
      if (nameEl) data.name = nameEl.value.trim();
      if (cfgEl) data.config_text = cfgEl.value;
      editor.updateNodeDataFromId(id, data);
    });
  }

  async function save() {
    syncNodeFields();
    const status = document.getElementById('status');
    const name = document.getElementById('wf-name').value.trim();
    if (!name) { status.textContent = 'Name required'; return; }

    const payload = {
      id: document.getElementById('wf-id').value || undefined,
      name: name,
      graph: toEngineGraph(),
    };

    status.textContent = 'Saving…';
    try {
      const res = await fetch('/api/workflow/save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (res.ok) {
        document.getElementById('wf-id').value = json.id;
        status.textContent = 'Saved ✓';
        // Server-generated webhook HMAC secret is shown once so the sender can be configured.
        if (json.webhook_secret) {
          window.alert('Webhook secret (store it now, shown once):\n\n' + json.webhook_secret);
        }
      } else {
        status.textContent = 'Error: ' + (json.error || res.status);
      }
    } catch (e) {
      status.textContent = 'Network error';
    }
  }

  document.getElementById('save').addEventListener('click', save);
})();
