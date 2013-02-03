(function() {

  var Shape = Two.Shape = function() {

    Shape.MakeGetterSetter(this, Shape.Properties);

    this.translation = new Two.Vector();
    this.rotation = 0.0;
    this.scale = 1.0;

    this.fill = '#fff';
    this.stroke = '#000';
    this.linewidth = 1.0;
    this.opacity = 1.0;
    this.visible = true;

    this.join = 'round';
    this.miter = 'round';

    // Extra bind for translation

    this.translation.bind('change', _.bind(function(property) {
      this.trigger('change', this.id, 'translation', this.translation, this);
    }, this));

  };

  _.extend(Shape, {

    Properties: [
      'rotation',
      'scale',
      'fill',
      'stroke',
      'linewidth',
      'opacity',
      'visible',
      'join',
      'miter'
    ],

    MakeGetterSetter: function(shape, properties) {

      if (!_.isArray(properties)) {
        properties = [properties];
      }

      _.each(properties, function(k) {

        var secret = '_' + k;

        Object.defineProperty(shape, k, {
          get: function() {
            return this[secret];
          },
          set: function(v) {
            this[secret] = v;
            this.trigger(Two.Events.change, this.id, k, v, this);
          }
        });

      });

    }

  });

  _.extend(Shape.prototype, Backbone.Events, {

  });

})();