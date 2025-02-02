

  (() => {
    const { registerBlockType } = wp.blocks;
    const {
      TextControl,
      Button,
      PanelBody,
      ToggleControl,
      ColorPalette,
      RangeControl,
    } = wp.components;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { createElement: el, useEffect } = wp.element;
  
    registerBlockType('wp-document-signature/signature-field', {
      title: 'Signature Field',
      icon: 'edit',
      category: 'wp-document-signature',
      attributes: {
        signature: { type: 'string', default: '' }, // Stores base64 signature
        signatureLabel: { type: 'string', default: 'Sign Here' },
        labelColor: { type: 'string', default: '#000000' },
        backgroundColor: { type: 'string', default: '#ffffff' },
        borderColor: { type: 'string', default: '#cccccc' },
        borderRadius: { type: 'number', default: 4 },
        required: { type: 'boolean', default: false },
        canvasWidth: { type: 'number', default: 400 },
        canvasHeight: { type: 'number', default: 150 },
        blockId: { type: 'string', default: '' }, // Unique ID for multiple instances
      },
  
      edit: ({ attributes, setAttributes, clientId }) => {
        const {
          signature,
          signatureLabel,
          labelColor,
          backgroundColor,
          borderColor,
          borderRadius,
          canvasWidth,
          canvasHeight,
          blockId,
        } = attributes;
  
        useEffect(() => {
          if (!blockId) {
            setAttributes({ blockId: `signature-${clientId}` });
          }
  
          const canvas = document.getElementById(`canvas-${clientId}`);
          if (!canvas) return;
  
          const ctx = canvas.getContext('2d');
          let isDrawing = false;
  
          const startDrawing = (event) => {
            isDrawing = true;
            ctx.beginPath();
            ctx.moveTo(event.offsetX, event.offsetY);
          };
  
          const draw = (event) => {
            if (!isDrawing) return;
            ctx.lineTo(event.offsetX, event.offsetY);
            ctx.stroke();
          };
  
          const stopDrawing = () => {
            isDrawing = false;
          };
  
          canvas.addEventListener('mousedown', startDrawing);
          canvas.addEventListener('mousemove', draw);
          canvas.addEventListener('mouseup', stopDrawing);
  
          return () => {
            canvas.removeEventListener('mousedown', startDrawing);
            canvas.removeEventListener('mousemove', draw);
            canvas.removeEventListener('mouseup', stopDrawing);
          };
        }, [clientId]);
  
        const clearSignature = () => {
          setAttributes({ signature: '' });
          const canvas = document.getElementById(`canvas-${clientId}`);
          if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
          }
        };
  
        const saveSignature = () => {
          const canvas = document.getElementById(`canvas-${clientId}`);
          if (canvas) {
            const dataUrl = canvas.toDataURL();
            setAttributes({ signature: dataUrl });
          }
        };
  
        return el(
          'div',
          useBlockProps(),
          el(InspectorControls, {},
            el(PanelBody, { title: 'Field Settings', initialOpen: true },
              el(TextControl, {
                label: 'Label',
                value: signatureLabel,
                onChange: (newLabel) => setAttributes({ signatureLabel: newLabel }),
              }),
              el(RangeControl, {
                label: 'Border Radius',
                value: borderRadius,
                onChange: (value) => setAttributes({ borderRadius: value }),
                min: 0,
                max: 50,
              }),
              el(ToggleControl, {
                label: 'Required Field',
                checked: attributes.required,
                onChange: (value) => setAttributes({ required: value }),
              })
            )
          ),
          el('label', { style: { color: labelColor, fontSize: '16px', fontWeight: 'bold' } }, signatureLabel),
          el('canvas', {
            id: `canvas-${clientId}`,
            width: canvasWidth,
            height: canvasHeight,
            style: {
              backgroundColor: backgroundColor,
              border: `2px solid ${borderColor}`,
              borderRadius: `${borderRadius}px`,
              display: 'block',
            },
          }),
          el('div', { style: { marginTop: '10px' } },
            el(Button, { onClick: saveSignature }, 'Save Signature'),
            el(Button, { onClick: clearSignature, style: { marginLeft: '10px' } }, 'Clear Signature')
          )
        );
      },
  
      save: ({ attributes }) => {
        const { signature, signatureLabel, backgroundColor, borderColor, borderRadius, canvasWidth, canvasHeight, blockId } = attributes;
  
        return el(
          'div',
          useBlockProps.save(),
          el('label', { style: { color: '#000', fontSize: '16px', fontWeight: 'bold' } }, signatureLabel),
          el('canvas', {
            id: `frontend-${blockId}`,
            width: canvasWidth,
            height: canvasHeight,
            style: {
              backgroundColor: backgroundColor,
              border: `2px solid ${borderColor}`,
              borderRadius: `${borderRadius}px`,
              display: 'block',
            },
          }),
          el('input', { type: 'hidden', id: `signature-data-${blockId}`, name: `signature-${blockId}`, value: signature }),
          el('script', {
            dangerouslySetInnerHTML: {
              __html: `
                (function() {
                  var canvas = document.getElementById('frontend-${blockId}');
                  var ctx = canvas.getContext('2d');
                  let isDrawing = false;
                  
                  canvas.addEventListener('mousedown', function(event) {
                    isDrawing = true;
                    ctx.beginPath();
                    ctx.moveTo(event.offsetX, event.offsetY);
                  });
  
                  canvas.addEventListener('mousemove', function(event) {
                    if (!isDrawing) return;
                    ctx.lineTo(event.offsetX, event.offsetY);
                    ctx.stroke();
                  });
  
                  canvas.addEventListener('mouseup', function() {
                    isDrawing = false;
                  });
  
                  document.getElementById('save-signature-btn-${blockId}').addEventListener('click', function() {
                    var dataUrl = canvas.toDataURL();
                    document.getElementById('signature-data-${blockId}').value = dataUrl;
                  });
  
                  document.getElementById('clear-signature-btn-${blockId}').addEventListener('click', function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    document.getElementById('signature-data-${blockId}').value = '';
                  });
                })();
              `,
            },
          }),
          el('div', { style: { marginTop: '10px' } },
            el('button', { id: `save-signature-btn-${blockId}`, type: 'button' }, 'Save Signature'),
            el('button', { id: `clear-signature-btn-${blockId}`, type: 'button', style: { marginLeft: '10px' } }, 'Clear Signature')
          )
        );
      }
    });
  })();
  
  